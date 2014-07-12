<?php
/**
 * Form for handling uploads and special page.
 *
 * @ingroup SpecialPage
 * @ingroup Upload
 */
class SpecialMultipleUpload extends SpecialUpload {

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

        // Process upload or show a form
        if ( $this->mTokenOk && !$this->mCancelUpload && $this->mUploadClicked ) {
            $this->recurseThroughUpload();
        }
        else {
            $this->showUploadForm( $this->getUploadForm() );
        }

        // Cleanup
        if ( $this->mUpload ) {
            $this->mUpload->cleanupTempFile(); //todo check if we want this behavior
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
    protected function userChecks() { // todo rename
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
     * Initialize instance variables from request todo move this up
     */
    protected function loadRequest() {
        // load request; initiating class directly TODO put the explanation here
        $this->mRequest = $request = new WebRequestMultiple;

        $this->mDesiredDestName = ''; //todo get this to work with renameForm only
        $this->mLicense = $request->getText( 'wpLicense' );
        $this->mDestWarningAck = $request->getText( 'wpDestFileWarningAck' );
        $this->mWatchthis = $request->getBool( 'wpWatchthis' ) && $this->getUser()->isLoggedIn();
        $this->mCopyrightStatus = $request->getText( 'wpUploadCopyStatus' );
        $this->mCopyrightSource = $request->getText( 'wpUploadSource' );
        $this->mForReUpload = $request->getBool( 'wpForReUpload' ); // updating a file todo rename form?
        $this->mCancelUpload = $request->getCheck( 'wpCancelUpload' );
        $this->mUploadClicked = $this->getUploadClicked( $request );
        $this->mComment = $this->getComment( $request );

        $this->mIgnoreWarning = $request->getCheck( 'wpIgnoreWarning' )
            || $request->getCheck( 'wpUploadIgnoreWarning' );

        // If it was posted check for the token (no remote POST'ing with user credentials)
        $token = $request->getVal( 'wpEditToken' );
        $this->mTokenOk = $this->getUser()->matchEditToken( $token );
    }

    /*
     *
     */
    protected function getUploadClicked ( WebRequestMultiple $request ) {
        if (
            $request->wasPosted() &&
            ( $request->getCheck( 'wpUpload' ) || $request->getCheck( 'wpUploadIgnoreWarning' ) )
        ) return true;

        return false;
    }

    /*
     *
     */
    protected function getComment ( WebRequestMultiple $request, $commentDefault = null) {
        $commentMsg = wfMessage( 'upload-default-description' )->inContentLanguage();
        if ( !$this->mForReUpload && !$commentMsg->isDisabled() ) {
            $commentDefault = $commentMsg->plain();
        }

        return $request->getText( 'wpUploadDescription', $commentDefault );
    }

    /*
     * todo document
     */
    protected function recurseThroughUpload() {
        $request = $this->mRequest;
        $count = $this->countUploads();

        for( $i = 0 ; $i < $count ; $i++ ) {
            // injecting property (current key in recursion) into $request
            // bit hackish, see ./MultipleUpload.php for details todo document better
            $request->recursionKey = $i;

            $this->mUpload = UploadBase::createFromRequest( $request );
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
     * Checks are made in SpecialUpload::execute()
     */
    protected function processUpload() {
        $details = $this->mUpload->verifyUpload();
        if ( $details['status'] != UploadBase::OK ) {
            $this->processVerificationError( $details );
            return;
        }

        $permErrors = $this->mUpload->verifyTitlePermissions( $this->getUser() );
        if ( $permErrors !== true ) {
            $code = array_shift( $permErrors[0] );
            $this->showRecoverableUploadError( $this->msg( $code, $permErrors[0] )->parse() );
            return;
        }

        if ( !$this->mIgnoreWarning ) {
            $warnings = $this->mUpload->checkWarnings();
            if ( $this->showUploadWarning( $warnings ) ) {
                return;
            }
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
            $this->showUploadError( $this->getOutput()->parse( $status->getWikiText() ) );
            return;
        }

       $this->afterSuccess();
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
    protected function getUploadForm( $message = '', $sessionKey = '', $hideIgnoreWarning = false ) {
        $context = new DerivativeContext( $this->getContext() );
        $context->setTitle( $this->getTitle() ); // Remove subpage todo?

        $form = new MultipleUploadForm( array(
            'watch' => $this->getWatchCheck(),
            'forreupload' => $this->mForReUpload,
            'sessionkey' => $sessionKey,
            'hideignorewarning' => $hideIgnoreWarning,
            'destwarningack' => (bool)$this->mDestWarningAck,
            'description' => $this->mComment,
            'texttop' => $this->uploadFormTextTop,
            'textaftersummary' => $this->uploadFormTextAfterSummary,
            'destfile' => '',
        ), $context );

        return $form;
    }

    /**
     * Provides output to the user for a result of UploadBase::verifyUpload
     *
     * @param array $details result of UploadBase::verifyUpload
     * @throws MWException
     */
    protected function processVerificationError( $details ) {
        global $wgFileExtensions;

        switch ( $details['status'] ) {

            /** Statuses that only require name changing **/
            case UploadBase::MIN_LENGTH_PARTNAME:
                $this->showRecoverableUploadError( $this->msg( 'minlength1' )->escaped() );
                break;
            case UploadBase::ILLEGAL_FILENAME:
                $this->showRecoverableUploadError( $this->msg( 'illegalfilename',
                    $details['filtered'] )->parse() );
                break;
            case UploadBase::FILENAME_TOO_LONG:
                $this->showRecoverableUploadError( $this->msg( 'filename-toolong' )->escaped() );
                break;
            case UploadBase::FILETYPE_MISSING:
                $this->showRecoverableUploadError( $this->msg( 'filetype-missing' )->parse() );
                break;
            case UploadBase::WINDOWS_NONASCII_FILENAME:
                $this->showRecoverableUploadError( $this->msg( 'windows-nonascii-filename' )->parse() );
                break;

            /** Statuses that require reuploading **/
            case UploadBase::EMPTY_FILE:
                $this->showUploadError( $this->msg( 'emptyfile' )->escaped() );
                break;
            case UploadBase::FILE_TOO_LARGE:
                $this->showUploadError( $this->msg( 'largefileserver' )->escaped() );
                break;
            case UploadBase::FILETYPE_BADTYPE:
                $msg = $this->msg( 'filetype-banned-type' );
                if ( isset( $details['blacklistedExt'] ) ) {
                    $msg->params( $this->getLanguage()->commaList( $details['blacklistedExt'] ) );
                } else {
                    $msg->params( $details['finalExt'] );
                }
                $extensions = array_unique( $wgFileExtensions );
                $msg->params( $this->getLanguage()->commaList( $extensions ),
                    count( $extensions ) );

                // Add PLURAL support for the first parameter. This results
                // in a bit unlogical parameter sequence, but does not break
                // old translations
                if ( isset( $details['blacklistedExt'] ) ) {
                    $msg->params( count( $details['blacklistedExt'] ) );
                } else {
                    $msg->params( 1 );
                }

                $this->showUploadError( $msg->parse() );
                break;
            case UploadBase::VERIFICATION_ERROR:
                unset( $details['status'] );
                $code = array_shift( $details['details'] );
                $this->showUploadError( $this->msg( $code, $details['details'] )->parse() );
                break;
            case UploadBase::HOOK_ABORTED:
                if ( is_array( $details['error'] ) ) { # allow hooks to return error details in an array
                    $args = $details['error'];
                    $error = array_shift( $args );
                } else {
                    $error = $details['error'];
                    $args = null;
                }

                $this->showUploadError( $this->msg( $error, $args )->parse() );
                break;
            default:
                throw new MWException( __METHOD__ . ": Unknown value `{$details['status']}`" );
        }
    }

}