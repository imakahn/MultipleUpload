<?php
/**
 * Special page and the controller for uploading multiple files in one submission.
 * See MultipleUploadForm, MultipleRenameForm for views.
 *
 * @ingroup Extensions
 * @ingroup Upload
 */
class SpecialMultipleUpload extends SpecialUpload {
    protected $mRecoverableFiles, $mUnRecoverableFiles;
    protected $mRenameClicked, $mRenamedFiles, $mGallery;
    protected $mRecursionKey; //currently unused

    /**
     * Constructor : initialise object
     */
    public function __construct() {
        // cannot call two layers of parent::_construct, so calling method directly
        SpecialPage::__construct( 'MultipleUpload', '', true );
    }

    /**
     * Special page entry point -- todo document the flow of this
     */
    public function execute( $par ) {
        $this->setHeaders();
        $this->outputHeader();

        if ( !UploadBase::isEnabled() ) {
            throw new ErrorPageError( 'uploaddisabled', 'uploaddisabledtext' );
        }
        $this->checkReadOnly();
        $this->userChecks();

        // add our upload handlers to the list
        UploadBase::$uploadHandlers = array_merge( UploadBase::$uploadHandlers, array(
                'MultipleFile',
                'MultipleStash'
            ) );

        // populate properties
        $this->loadRequest();

        // Process upload or show a form -- be it uploading or renaming files
        if ( $this->mTokenOk && ( $this->mUploadClicked || $this->mRenameClicked ) ) {
            // actually process the upload
            $this->recurseThroughUpload();

            if ( !empty( $this->mRecoverableFiles ) ) {
                $this->showForm( 'rename' );
            }
            else {
                $this->afterSuccess( $this->mUnRecoverableFiles );
            }
        }
        else { // show the form
            $this->showForm( 'upload' );
        }

        if ( $this->mUpload ) { // Cleanup
            $this->mUpload->cleanupTempFile();
        }
    }

    /**
    * Initialize instance variables from request
    */
    protected function loadRequest() {
        $this->mRequest = $request = $this->getRequest();

        $this->mLicense = $request->getText( 'wpLicense' );
        $this->mCopyrightStatus = $request->getText( 'wpUploadCopyStatus' );
        $this->mCopyrightSource = $request->getText( 'wpUploadSource' );
        $this->mUploadClicked = $this->getClicked( $request, 'wpMultipleUpload' );
        if ( $this->mRenameClicked = $this->getClicked( $request, 'wpMultipleRename' ) ) {
            $this->getRenamedFiles( $request );
        };
        $this->mIgnoreWarning = $request->getCheck( 'wpIgnoreWarning' );

        $token = $request->getVal( 'wpEditToken' ); // check for and validate token
        $this->mTokenOk = $this->getUser()->matchEditToken( $token );

        $this->mGallery = unserialize( $request->getText( 'wpData' ) );
        if ( empty( $this->mGallery ) ) {
            $this->mGallery = array();
        }
    }

    /* sets mRecursionKey, reinitializes the class properties for recursion, etc
     *
     */
    protected function recurseThroughUpload() {
        $count = $this->getCount();
        $request = $this->mRequest;

        for( $key = 0 ; $key < $count ; $key++ ) {
            $this->mRecursionKey = $key; //used by storeError, gallery
            $this->mDesiredDestName = $_FILES['wpUploadMultipleFile']['name'][$key];

            // this will either call MultipleFile or MultipleStash, depending on the value of wpSourceType
            $this->mUpload = $upload = UploadBase::createFromRequest( $request );

            if ( $upload instanceof UploadFromMultipleFile ) {
                $upload->reInitializePathInfo( $key );
            }
            elseif ( $upload instanceof UploadFromMultipleStash ) {
                $renamedFile = $this->mRenamedFiles[$key];
                $upload->reInitializePathInfo( $renamedFile['fileKey'], $renamedFile['name'] );
            }
            $this->processUpload();
        }

    }

    /**
     * Do the upload.
     */
    protected function processUpload() {
        $details = $this->mUpload->verifyUpload();
        if ( $details['status'] != UploadBase::OK ) {
            $this->errorRouter( $details );
            return;
        }

        $permErrors = $this->mUpload->verifyTitlePermissions( $this->getUser() );
        if ( $permErrors !== true ) { // UploadBase should be cleaned up for consistent return values from validators..
            // letting this one go for now, cost of hunting down every last error possibility/constant too high
            $code = array_shift( $permErrors[0] );
            $this->errorRouter( $code );
            return;
        }

        if( !$this->mIgnoreWarning ) {
            if (  $warnings = $this->mUpload->checkWarnings() ){
                $this->errorRouter( $warnings );
                return;
            }
        }

        // Get the page text if this is not a reupload
        if ( !$this->mRenameClicked ) {
            $pageText = self::getInitialPageText( $this->mComment, $this->mLicense,
                $this->mCopyrightStatus, $this->mCopyrightSource );
        } else {
            $pageText = false;
        }

        // todo rework this, and we can remove our dependency on SpecialUpload
        $status = $this->mUpload->performUpload( $this->mComment, $pageText, $this->mWatchthis, $this->getUser() );
        if ( !$status->isGood() ) {
            $this->showUploadError( $this->getOutput()->parse( $status->getWikiText() ) );
            return;
        }

        $this->mLocalFile = $this->mUpload->getLocalFile();

        // add file to the gallery
        $this->mGallery[] = 'File:' . $this->mLocalFile->getName();
    }

    /*
     * Success! Now let's finish up
     * This function is probably gold to anyone looking for documentation on content creation in mediawiki
     * todo move messages to i18n file
     */
    protected function afterSuccess( $unRecoverable ){
        $userTitle = $this->getUser()->getUserPage(); // actually gives us the title object, page object is next
        $userPage = WikiPage::factory( $userTitle );
        $oldText = ContentHandler::getContentText( $userPage->getContent() );

        $galleryText = "$oldText\n\n" . '==' . 'Images uploaded ' . date( 'n-j-y, g:ia' ) . '==' . "\n";
        $galleryText .= "<gallery>\n" . implode( "\n", $this->mGallery ) .  "\n</gallery>";

        if ( !empty( $unRecoverable ) ) {
            $galleryText .= 'The following files failed to upload: \n' . implode( ' ', $unRecoverable ) . $galleryText;
        }

        $galleryContent = ContentHandler::makeContent( $galleryText, $userTitle );
        $userPage->doEditContent( $galleryContent, 'Batch image upload' );

        $this->mUploadSuccessful = true;
        // without this, the images show up as broken links--yet action=purge doesn't actually update the link table..
        // todo only works about half the time, have to figure out root cause (doEditSection supposedly takes car of all
        // this, but it's clearly not doing so)
        $url = $userTitle->getFullURL() . '?action=purge';
        $this->getOutput()->redirect( $url );
    }

    /*
     *
     */
    protected function showForm( $name, $message = '' ) {
        $context = new DerivativeContext( $this->getContext() );
        $context->setTitle( $this->getTitle() );

        $call = 'get' . ucfirst($name) . 'Form';
        $form = $this->$call( $context );

        if ( $form instanceof HTMLForm ) {
            $form->addPreText( $message ); // add error message todo add css error class?
            $form->show();
        } else {
            $this->getOutput()->addHTML( $form );
        }
    }

    /*
     *
     */
    protected function getUploadForm( $context ) {
        $form = new MultipleUploadForm( array(
            'watch' => $this->getWatchCheck(),
            'destwarningack' => (bool)$this->mDestWarningAck,
            'description' => $this->mComment,
            'texttop' => $this->uploadFormTextTop,
            'textaftersummary' => $this->uploadFormTextAfterSummary,
            'destfile' => '',
        ), $context );

        return $form;
    }

    /*
     *
     */
    protected function getRenameForm( $context ) {
        return new MultipleRenameForm( $this->mRecoverableFiles, $this->mGallery, $context );
    }

    /*
     *
     */
    protected function errorRouter( $error ) {
        $warnings = array(
            'badfilename',
            'exists',
            'duplicate',
            'duplicate-archive',
            'file-deleted-duplicate',
            'bad-prefix',
            'page-exists',
            'exists-normalized',
            'thumb',
            'thumb-name',
            'was-deleted'
        );

        if ( is_array( $error ) ) {
            if ( isset( $error['status'] ) ) {
                $status = $error['status'];

                switch ( $status ) {
                    /** Statuses that only require name changing **/
                    case UploadBase::MIN_LENGTH_PARTNAME:
                    case UploadBase::ILLEGAL_FILENAME:
                    case UploadBase::FILENAME_TOO_LONG:
                    case UploadBase::FILETYPE_MISSING:
                    case UploadBase::WINDOWS_NONASCII_FILENAME:
                        // UploadBase method name is weird--this actually gives us the msg constant
                        $msg = $this->mUpload->getVerificationErrorCode( $status );
                        $this->storeError( $msg, true );
                        break;

                    /** Statuses that require reuploading **/
                    case UploadBase::EMPTY_FILE:
                    case UploadBase::FILE_TOO_LARGE:
                    case UploadBase::FILETYPE_BADTYPE:
                    case UploadBase::VERIFICATION_ERROR:
                    case UploadBase::HOOK_ABORTED:
                        $msg = $this->mUpload->getVerificationErrorCode( $status );
                        $this->storeError( $msg, false );
                        break;
                    default:
                        throw new MWException( __METHOD__ . ": Unknown value $status" ); //todo handle nicely
                }
            }
            else {
                foreach ( $error as $key => $value) { // todo support 'warning' key for more specific output
                    if ( in_array( $key, $warnings ) ) {
                        $this->storeError( $key, true );
                        break;
                    }
                }
            }
        }
        else {
            // must be a mUpload->verifyTitlePermissions error
            $this->storeError( $error, false );
        }
    }

    /*
     *
     */
    protected function storeError( $msg, $recoverable ) {
        $name = $this->mDesiredDestName;

        if ( $recoverable !== false ) {
            // this is recoverable (just needs a rename), so stash the file:
            $file = $this->mUpload->stashFile( $this->getUser() );
            $this->mRecoverableFiles[] = [ 'file' => $file, 'name' => $name, 'msg' => $msg ];
        }
        else{
            $this->mUnRecoverableFiles[] = [ 'name' => $name, 'msg' => $msg ];
        }
    }

    /*
     *
     */
    protected function userChecks() {
        $user = $this->getUser();
        $groups = $user->getGroups();
        $elevatedGroups = array( 'bureaucrat', 'sysop');

        $permissionRequired = UploadBase::isAllowed( $user );
        if ( $permissionRequired !== true ) {
            throw new PermissionsError( $permissionRequired );
        }
        if ( $user->isBlocked() ) {
            throw new UserBlockedError( $user->getBlock() );
        }

        foreach ( $groups as $group ) {
            if( in_array( $group, $elevatedGroups ) ) { //todo putting this on the job queue would be better
                ini_set( 'max_execution_time', 500 );
            }
        }
    }

    /*
     *
     */
    protected function getRenamedFiles( WebRequest $request ) {
        for ( $num = 1 ; $num < ini_get( 'max_file_uploads' ) ; $num++ ) {

            if ( $request->getCheck( 'wpRename' . $num ) ) {
                $this->mRenamedFiles[] = array(
                    'name' => $request->getText( 'wpRename' . $num ),
                    'fileKey' => $request->getText( 'wpRenameKey' . $num )
                );
            }
            else break;
        }
    }

    /*
     *
     */
    protected function getClicked( WebRequest $request, $label ) {
        if ( $request->wasPosted() && $request->getCheck( $label ) ) {
            return true;
        }
        return false;
    }

    /*
     *
     */
    protected function getCount() {
        if ( $this->mRenameClicked ) {
            return count( $this->mRenamedFiles );
        }

        return count($_FILES['wpUploadMultipleFile']['name']);
    }

}