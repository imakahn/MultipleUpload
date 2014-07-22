<?php

/*
 * Very similar to UploadFromStash, with the necessary changes for recursion
 * Could not extend that class directly because of private properties
 */
class UploadFromMultipleStash extends UploadBase {
    protected $mFileKey, $mVirtualTempPath, $mFileProps, $mSourceType;

    // an instance of UploadStash
    private $stash;

    //LocalFile repo
    private $repo;

    /**
     * @param $user User
     * @param $stash UploadStash
     * @param $repo FileRepo
     */
    public function __construct( $user = false, $stash = false, $repo = false ) {
        // user object. sometimes this won't exist, as when running from cron. //todo shouldn't be be getting one?
        $this->user = $user;

        if ( $repo ) {
            $this->repo = $repo;
        } else {
            $this->repo = RepoGroup::singleton()->getLocalRepo();
        }

            $this->stash = new UploadStash( $this->repo, $this->user );
    }

    public function reInitializePathInfo ( $key, $name ) {
        if ( $this->isValidKey( $key ) !== true ) return;

        $this->mFileProps = $this->stash->getFileProps( $key );
        $this->mDesiredDestName = $name;
        $this->mRemoveTempFile = false;
        $this->mFileKey = $key;

        $metadata = $this->stash->getMetadata( $key );
        $this->mVirtualTempPath = $metadata['us_path'];
        $this->mFileSize = $metadata['us_size'];
        $this->mSourceType = $metadata['us_source_type']; //todo check this one
        $this->mTempPath = $tempPath = $this->getRealPath( $metadata['us_path'] );
    }

    /**
     * we don't have the data we need yet
     */
    public function initializeFromRequest( &$request ) {
    }

    public static function isValidRequest( $request ) {
        return true;
    }

    /**
     * @param $key string
     * @return bool
     */
    public static function isValidKey( $key ) {
        // this is checked in more detail in UploadStash
        return (bool)preg_match( UploadStash::KEY_FORMAT_REGEX, $key );
    }

    /**
     * @return string
     */
    public function getSourceType() {
        return $this->mSourceType;
    }

    /**
     * Get the base 36 SHA1 of the file
     * @return string
     */
    public function getTempFileSha1Base36() {
        return $this->mFileProps['sha1'];
    }

    /**
     * Stash the file.
     *
     * @param $user User
     * @return UploadStashFile
     */
    public function stashFile( User $user = null ) {
        // replace mLocalFile with an instance of UploadStashFile, which adds some methods
        // that are useful for stashed files.
        $this->mLocalFile = parent::stashFile( $user );
        return $this->mLocalFile;
    }

    /**
     * This should return the key instead of the UploadStashFile instance, for backward compatibility.
     * @return String
     */
    public function stashSession() {
        return $this->stashFile()->getFileKey();
    }

    /**
     * Remove a temporarily kept file stashed by saveTempUploadedFile().
     * @return bool success
     */
    public function unsaveUploadedFile() {
        return $this->stash->removeFile( $this->mFileKey );
    }

    /**
     * Perform the upload, then remove the database record afterward.
     * @param $comment string
     * @param $pageText string
     * @param $watch bool
     * @param $user User
     * @return Status
     */
    public function performUpload( $comment, $pageText, $watch, $user ) {
        $result = parent::performUpload( $comment, $pageText, $watch, $user );
        $this->unsaveUploadedFile();
        return $result;
    }

} 