<?php

require 'vendor/autoload.php';

use Commando\Command;
use Stringy\StaticStringy as S;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path as P;

function writeLn($line)
{
    fwrite(STDOUT, $line.PHP_EOL);
}

function writeLnErr($line)
{
    fwrite(STDERR, $line.PHP_EOL);
}

function run($cmd, $successCodes = null)
{
    $successCodes = $successCodes ?? [0];
    $process = new Process($cmd);
    $process->run();
    if (!in_array($process->getExitCode(), $successCodes)) {
        writeLnErr($process->getErrorOutput());
        exit;
    }
    $output = $process->getOutput();
    $lines = collect(mb_split('[\r\n]{1,2}', $output))->filter();
    return $lines;
}

// Define command line interface
$cmd = new Command();
$cmd->setHelp('Formats modified php files using git and php-cs-fixer.');
$cmd->argument()
    ->description('A git directory with PHP files to fix');
$cmd->option('o')
    ->aka('options')
    ->description('Options to pass to the php-cs-fixer (prefix value with \), e.g. -o "\--dry-run --diff"')
    ->must(function ($v) {
        return S::startsWith($v, '\\');
    })
    ->map(function ($v) {
        return S::substr($v, 1);
    });

$dir = $cmd[0] ?? getcwd();
writeLn("Checking directory {$dir}");

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
        if (!S::endsWith($fileName, '.php')) {
            return null;
        }

        // Build file path
        $filePath = P::join($dir, $fileName);
        return $filePath;
    })
    ->filter()
    ->uniqueStrict();

if ($files->count() === 0) {
    writeLn('Nothing to fix.');
    exit;
}

$fixCmdFiles = $files->map(function ($f) { return "\"{$f}\""; })->implode(' ');
$fixCmd = 'php-cs-fixer fix '.$fixCmdFiles;
$fixOptions = $cmd['o'];

// Look for config if none specified
if (!S::contains($fixOptions, '--config')) {
    $fixConfig = P::join($dir, '.php_cs.dist');
    if (file_exists($fixConfig)) {
        writeLn('Using config '.$fixConfig);
        $fixCmd .= ' --config "'.$fixConfig.'"';
    }
}

// Add fixer options to fix command
if ($fixOptions !== null) {
    $fixCmd .= ' '.$fixOptions;
}

// Fix files
writeLn("Fixing {$files->count()} file(s)");
$fixResult = run($fixCmd, [0, 4, 8]);
writeLn($fixResult->implode(PHP_EOL));
