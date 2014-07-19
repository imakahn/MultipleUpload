<?php
/**
 * Form for handling uploads and special page.
 *
 * @ingroup SpecialPage
 * @ingroup Upload
 */
class SpecialMultipleUpload extends SpecialUpload {
    protected $mRecoverableFiles, $mUnRecoverableFiles, $mRecursionKey;
    protected $mRenameClicked, $mRenamedFiles;

    /**
     * Constructor : initialise object
     */
    public function __construct( ) {
        // cannot call two layers of parent::_construct, so calling method directly
        SpecialPage::__construct( 'MultipleUpload', '', true );
    }

    /**
     * Special page entry point
     */
    public function execute( $par ) {
        $this->setHeaders();
        $this->outputHeader();
        $this->preUploadChecks();

        // add us to the list of valid upload handlers
        UploadBase::$uploadHandlers[] = 'MultipleFile';

        // populate properties
        $this->loadRequest();

        # Unsave the temporary file in case this was a cancelled upload
        if ( $this->mCancelUpload ) {
            if ( !$this->unsaveUploadedFile() ) {
                # Something went wrong, so unsaveUploadedFile showed a warning
                return;
            }
        }

        // Process upload or show a form -- be it uploading or renaming files //todo clean this up
        if ( $this->mTokenOk && !$this->mCancelUpload ) {
            if ( $this->mUploadClicked ) {
                $this->recurseThroughUpload();

                if ( !empty( $this->mRecoverableFiles ) ) {
                /* todo don't need to do this, just need to set the parameter (already done) and
                 * run through the recursion again. have all functions check for that param
                 */
                    $this->showUploadForm( $this->getRenameForm() );
                }
                else {
                    $this->afterSuccess();
                }
            }
            elseif ( $this->mRenameClicked ) { //todo make into function
                if ( isset( $this->mRenamedFiles ) ){
                    foreach ( $this->mRenamedFiles as $file ) {

                    }

                }
                else {
                    $this->showUploadForm( $this->getRenameForm( "Error: You didn't rename any of the files!" ) );
                }
            }
        }
        else {
            $this->showUploadForm( $this->getUploadForm() );
        }

        // Cleanup //todo this especially
        if ( $this->mUpload ) {
            $this->mUpload->cleanupTempFile();
        }
    }

    /*
     *
     */
    protected function preUploadChecks() { //todo add upload max size per user here? or in uploadBase validation
        if ( !UploadBase::isEnabled() ) {
            throw new ErrorPageError( 'uploaddisabled', 'uploaddisabledtext' );
        }

        $this->userChecks();
        $this->checkReadOnly();
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
            if( in_array( $group, $elevatedGroups ) ) { //todo finish this

            }
        }
    }

    /**
     * Initialize instance variables from request
     */
    protected function loadRequest() {
        $this->mRequest = $request = $this->getRequest();

        $this->mDesiredDestName = ''; //todo get this to work with renameForm only
        $this->mLicense = $request->getText( 'wpLicense' );
        $this->mDestWarningAck = $request->getText( 'wpDestFileWarningAck' );
        $this->mWatchthis = $request->getBool( 'wpWatchthis' ) && $this->getUser()->isLoggedIn();
        $this->mCopyrightStatus = $request->getText( 'wpUploadCopyStatus' );
        $this->mCopyrightSource = $request->getText( 'wpUploadSource' );
        $this->mForReUpload = $request->getBool( 'wpForReUpload' ); // updating a file todo rename form?
        $this->mCancelUpload = $request->getCheck( 'wpCancelUpload' );
        $this->mUploadClicked = $this->getClicked( $request, 'wpMultipleUpload' );

        // If it was posted check for the token (no remote POSTing with user credentials)
        $token = $request->getVal( 'wpEditToken' );
        $this->mTokenOk = $this->getUser()->matchEditToken( $token );

        // Specific to rename form:
        $this->mRenameClicked = $renameClicked = $this->getClicked( $request, 'wpMultipleRename' );

        for ( $num = 1 ; $num < ini_get( 'max_file_uploads' ) ; $num++ ) {
            $this->mRenamedFiles[] = $request->getText( "wpRename$num" );
        }
    }

    /*
     *
     */
    protected function getClicked ( WebRequest $request, $label ) {
        if ( $request->wasPosted() && $request->getCheck( $label ) ) {
            return true;
        }

        return false;
    }

    /*
     *
     */
    protected function getUploadForm( $message = '', $sessionKey = '', $hideIgnoreWarning = false ) {
        $context = new DerivativeContext( $this->getContext() );
        $context->setTitle( $this->getTitle() );

        $form = new MultipleUploadForm( array(
            'watch' => $this->getWatchCheck(),
            'forreupload' => $this->mForReUpload,
            'sessionkey' => $sessionKey,
            'hideignorewarning' => $hideIgnoreWarning, //todo remove
            'destwarningack' => (bool)$this->mDestWarningAck,
            'description' => $this->mComment,
            'texttop' => $this->uploadFormTextTop,
            'textaftersummary' => $this->uploadFormTextAfterSummary,
            'destfile' => '',
        ), $context );

        # Add upload error message
        $form->addPreText( $message );

        return $form;
    }

    /*
     *
     */
    protected function getRenameForm( $message = '' ) {
        $context = new DerivativeContext( $this->getContext() );
        $context->setTitle( $this->getTitle() ); //todo set title here?

        $form = new MultipleRenameForm( $this->mRecoverableFiles, $context );

        # Add upload error message
        $form->addPreText( $message );

        return $form;
    }

    /*
     *
     */
    protected function recurseThroughUpload() {
        $request = $this->mRequest;
        $count = $this->countUploads();

        for( $key = 0 ; $key < $count ; $key++ ) {
            $this->mRecursionKey = $key;
            $this->mUpload = $upload = UploadBase::createFromRequest( $request, 'MultipleFile' );

            $upload->reInitializePathInfo( $key );
            $this->processUpload();
        }
    }

    /*
     *
     */
    public function countUploads( $key = 'wpUploadMultipleFile' ) {
        return count($_FILES[$key]['name']);
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

        if ( !$this->mIgnoreWarning ) {
            if (  $warnings = $this->mUpload->checkWarnings() ){
                $this->errorRouter( $warnings );
            }
            return;
        }

        // Get the page text if this is not a reupload
        if ( !$this->mForReUpload ) {
            $pageText = self::getInitialPageText( $this->mComment, $this->mLicense,
                $this->mCopyrightStatus, $this->mCopyrightSource );
        } else {
            $pageText = false;
        }

        $status = $this->mUpload->performUpload( $this->mComment, $pageText, $this->mWatchthis, $this->getUser() );
        if ( !$status->isGood() ) {
            $this->showUploadError( $this->getOutput()->parse( $status->getWikiText() ) ); //todo have to add to router!
            return;
        }
    }

    /*
     *
     */
    protected function afterSuccess(){
        $this->mLocalFile = $this->mUpload->getLocalFile();

        $this->mUploadSuccessful = true;
        $this->getOutput()->redirect( $this->mLocalFile->getTitle()->getFullURL() );
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
                        throw new MWException( __METHOD__ . ": Unknown value $status" );
                }
            }
            else {
                foreach ( $error as $key ) {
                    foreach ( $key as $value ) {
                        if ( in_array( $value, $warnings ) ) {
                            $this->storeError( $value, true );
                        }
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
        $key = $this->mRecursionKey;
        // specifically, we're calling the upload -webrequest- class here, not the upload class
        $name = $this->mRequest->getUpload( 'wpUploadMultipleFile' )->getName();
            $name = $name[$key];

        if ( $recoverable !== false ) {
            // this is recoverable (just needs a rename), so stash the file:
            $file = $this->mUpload->stashFile( $this->getUser() );
            //todo also do resize here? is it already time to refactor this?
            $this->mRecoverableFiles[] = [ 'file' => $file, 'name' => $name, 'msg' => $msg ];
        }
        else{
            $this->mUnRecoverableFiles[] = [ 'name' => $name[$key], 'msg' => $msg ];
        }
    }

}