@ECHO OFF
php ^
    -d phar.readonly=0 ^
    -d xdebug.remote_enable=0 ^
    -d xdebug.remote_autostart=0 ^
    -d display_errors=0 ^
    -d error_reporting=0 ^
    .\vendor\macfja\phar-builder\bin\phar-builder.php package composer.json -n