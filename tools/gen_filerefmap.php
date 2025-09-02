<?php declare(strict_types=1);

use danog\MadelineProto\FileRefExtractor\BuildMode\Ast;
use danog\MadelineProto\FileRefExtractor\FileRefGenerator;

require 'vendor/autoload.php';

// Cleanup schema
$schemaFile = __DIR__.'/../src/TL_filerefs.tl';
$schema = explode("\n", file_get_contents($schemaFile));
foreach ($schema as &$line) {
    $line = rtrim(trim($line), ';');
    if (str_starts_with($line, '//') || !$line) {
        continue;
    }
    $line = explode(" ", $line, 2);
    $line[0] = preg_replace('/#.*/', '', $line[0]);
    $line = implode(" ", $line);
    $id = Ast::crc($line);

    $line = explode(" ", $line, 2);
    $line[0] .= "#$id";
    $line = implode(" ", $line);
    $line .= ';';
}
$schema = implode("\n", $schema);
file_put_contents($schemaFile, $schema);

// Gen ref files

$list = __DIR__.'/../schemas/list.json';
$list = file_get_contents($list);
$list = json_decode($list, true);
$last = end($list);

FileRefGenerator::generate(
    $last,
    __DIR__."/../src/TL_telegram_v$last.tl",
    __DIR__.'/../src/file_ref_map.dat',
    __DIR__.'/../src/TL_filerefs_db.tl',
);

foreach ($list as $layer) {
    if ($layer < 186) {
        continue;
    }
    FileRefGenerator::generate(
        $layer,
        __DIR__."/../schemas/TL_telegram_v$layer.tl",
        __DIR__."/../schemas/TL_telegram_v{$layer}_file_ref_map.dat",
        __DIR__."/../schemas/TL_telegram_v{$layer}_filerefs_db.tl",
    );
}