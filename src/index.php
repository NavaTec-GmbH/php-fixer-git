<?php

require __DIR__.'/../vendor/autoload.php';

use Stringy\StaticStringy as S;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Process\Process;

(new SingleCommandApplication())
    ->setName('PHP Git Formatter')
    ->setDescription('Formats modified php files using git and php-cs-fixer.')
    ->addArgument('dir', InputArgument::OPTIONAL, 'A git directory with PHP files to fix')
    ->addOption('args', null, InputOption::VALUE_REQUIRED, 'Options to pass to the php-cs-fixer, e.g. --args="--dry-run --diff"')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $GLOBALS['input'] = $input;
        $GLOBALS['output'] = $output;
        main();
    })
    ->run();

/**
 * @return InputInterface
 */
function input()
{
    return $GLOBALS['input'];
}
/**
 * @return ConsoleOutputInterface
 */
function output()
{
    return $GLOBALS['output'];
}

function writelnErr($line = '')
{
    output()->getErrorOutput()->writeln($line);
}

function writeln($line = '')
{
    output()->writeln($line);
}

function main()
{
    $dir = input()->getArgument('dir') ?? getcwd();
    $fixArgsStr = input()->getOption('args') ?? '';
    writeln("Checking directory {$dir}");

    $gitCmd = "git -C \"{$dir}\" status -s";
    $gitStatus = run($gitCmd);

    $files = $gitStatus
        ->map(function ($s) use ($dir) {
            // Parse git status -s output line by line
            $parts = [];
            mb_ereg('^(.)(.) (.*?)(?: -> (.*?))?$', $s, $parts);

            // Check that file was modified
            $x = $parts[1];
            $y = $parts[2];
            if (($x === ' ' || $x === 'D') && ($y === ' ' || $y === 'D')) {
                return null;
            }

            // Get file name
            $left = $parts[3];
            $right = $parts[4];
            $fileName = $right ? $right : $left;
            if (!S::endsWith($fileName, '.php') || S::endsWith($fileName, '.blade.php')) {
                return null;
            }

            // Build file path
            $filePath = $dir.DIRECTORY_SEPARATOR.$fileName;
            return $filePath;
        })
        ->filter()
        ->uniqueStrict();

    if ($files->count() === 0) {
        writeln('Nothing to fix.');
        exit;
    }

    $fixArgs = collect(explode(' ', $fixArgsStr))
        ->map(fn ($a) => trim($a));

    if ($fixArgs->containsStrict('--config')) {
        writeln('Using specified config');
    } else {
        $fixConfig = $dir.DIRECTORY_SEPARATOR.'.php-cs-fixer.dist.php';
        if (file_exists($fixConfig)) {
            writeln('Using config '.$fixConfig);
            $fixArgsStr .= ' --config "'.$fixConfig.'"';
        } else {
            writeln('Using default config');
        }
    }

    // Chunk up files otherwise we get an error.
    $fixFileChunks = $files->map(function ($f) { return "\"{$f}\""; })->chunk(50);
    $multipleChunks = $fixFileChunks->count() > 1;
    if ($multipleChunks) {
        writeln("Fixing {$files->count()} files in chunks of 50 files.");
        writeln();
    } else {
        writeln("Fixing {$files->count()} file(s)");
    }

    // Fix all chunks
    foreach ($fixFileChunks as $i => $chunk) {
        if ($multipleChunks) {
            $chunkNo = $i + 1;
            writeln("Chunk #{$chunkNo}");
        }
        $fixCmdFiles = $chunk->implode(' ');
        $fixCmd = 'composer exec -- php-cs-fixer fix '.($fixArgsStr ?? '').' -- '.$fixCmdFiles;

        // Fix files
        $fixResult = run($fixCmd, [0, 4, 8]);
        writeln($fixResult->implode(PHP_EOL));
        if ($multipleChunks) {
            writeln();
        }
    }
}

function run($cmd, $successCodes = null)
{
    $successCodes = $successCodes ?? [0];
    $process = Process::fromShellCommandline($cmd);
    $process->run();
    $exitCode = $process->getExitCode();
    if (!in_array($exitCode, $successCodes)) {
        writelnErr("php-cs-fixer returned error code {$exitCode}");
        writelnErr($process->getErrorOutput());
        exit(1);
    }
    $output = $process->getOutput();
    $lines = collect(mb_split('[\r\n]{1,2}', $output))->filter();
    return $lines;
}
