<?php

class UploadFromMultipleFile extends UploadBase {
    /**
     * @var WebRequestMultipleUpload
     */
    protected $mUploadRequest = null;

    /**
     * @param $request WebRequestMultiple
     */
    function initializeFromRequest( &$request ) {
        $this->mUploadRequest = $uploadRequest = $request->getUpload( 'wpUploadMultipleFile' );

        $desiredDestName = $request->getText( 'wpDestFile' ); //todo deal with this--in renameForm
        if ( !$desiredDestName ) {
            $desiredDestName = $uploadRequest->getName();
        }

        $this->initializePathInfo( $desiredDestName, $uploadRequest->getTempName(), $uploadRequest->getSize() );
    }

    /**
     * Initialize the path information //todo doc
     */
    public function reInitializePathInfo( $key ) {
        $this->mTempPath = $this->mTempPath[$key];
        $this->mFileSize = $this->mFileSize[$key];
        $this->mDesiredDestName = $this->mDesiredDestName[$key];
    }

    /**
     * @param $request
     * @return bool
     */
    static function isValidRequest( $request ) {
        return true;
    }

    /**
     * @return array
     */
    public function verifyUpload() {
        # Check for a post_max_size or upload_max_size overflow, so that a //todo clean this up
        # proper error can be shown to the user
        if ( is_null( $this->mTempPath ) || $this->isEmptyFile() ) {
            if ( $this->mUploadRequest->isIniSizeOverflow() ) {
                return array(
                    'status' => UploadBase::FILE_TOO_LARGE,
                    'max' => min(
                        self::getMaxUploadSize( $this->getSourceType() ),
                        wfShorthandToInteger( ini_get( 'upload_max_filesize' ) ),
                        wfShorthandToInteger( ini_get( 'post_max_size' ) )
                    ),
                );
            }
        }

        return parent::verifyUpload(); //todo THIS is where I'm fixing the dscf etc validation right?
    }

    public function getSourceType() {
        return 'File';  // todo rename to MultipleFile and modify $wxMaxUploadSize['MultipleFile'] to match
    }

}