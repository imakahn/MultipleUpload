<?php

// Alert the user that this is not a valid access point to MediaWiki if they try to access it directly.
if ( !defined( 'MEDIAWIKI' ) ) {
    echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/MultipleUpload/MultipleUpload.php" );
EOT;
    exit( 1 );
}

$wgExtensionCredits[ 'specialpage' ][] = array(
    'path' => __FILE__,
    'name' => 'MultipleUpload',
    'author' => 'Andrew Kahn',
    'url' => 'https://www.mediawiki.org/wiki/Extension:MultipleUpload',
    'descriptionmsg' => 'multipleupload-desc',
    'version' => '0.1.0',
);

$wgSpecialPages[ 'MultipleUpload' ] = 'SpecialMultipleUpload'; // Tell MediaWiki about the special page and its class name
$wgExtensionMessagesFiles[ 'MultipleUpload' ] = __DIR__ . '/MultipleUpload.i18n.php'; // Location of the messages file
$wgAutoloadClasses[ 'UploadMultipleSourceField' ] = __DIR__ . '/UploadMultipleSourceField.php';
$wgAutoloadClasses[ 'MultipleUploadForm' ] = __DIR__ . '/MultipleUploadForm.php';
$wgAutoloadClasses[ 'MultipleRenameForm' ] = __DIR__ . '/MultipleRenameForm.php';
//$wgAutoloadClasses[ 'MultipleRenameField' ] = __DIR__ . '/MultipleRenameForm.php';
$wgAutoloadClasses[ 'SpecialMultipleUpload' ] = __DIR__ . '/SpecialMultipleUpload.php';
$wgAutoloadClasses[ 'UploadFromMultipleFile'] = __DIR__ . '/UploadFromMultipleFile.php';
