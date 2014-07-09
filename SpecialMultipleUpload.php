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
        SpecialPage::__construct( 'MultipleUpload', '', true );
    }

    /**
     * Special page entry point
     */
    public function execute( $par ) {
        $this->setHeaders();
        $this->outputHeader();
        $this->preUploadChecks();

        // populate properties
        $this->loadRequest();

        // Process upload or show a form
        if ( $this->mTokenOk && $this->mUploadClicked && !$this->mCancelUpload ) {
                $this->recursiveUpload();
        }
        else {
            $this->showUploadForm( $this->getUploadForm() );
        }

        // Cleanup
        if ( $this->mUpload ) {
            $this->mUpload->cleanupTempFile();
        }
    }

    public function isMultipleUpload() {
        $count = count($_FILES['wpUploadFile']['name']);
        if ( $count > 1 ) {
            return $count;
        }

        return false;
    }

    protected function preUploadChecks() { //todo add upload max size per user here? or in uploadBase validation
        if ( !UploadBase::isEnabled() ) {
            throw new ErrorPageError( 'uploaddisabled', 'uploaddisabledtext' );
        }

        $this->userChecks();
        $this->checkReadOnly();
    }

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
            if( in_array( $group, $elevatedGroups ) ) {
            }

        }
    }

    /**
     * Initialize instance variables from request
     */
    protected function loadRequest() {
        $this->mRequest = $request = new WebMultipleRequest; // load request; initiating class directly

        $this->mDesiredDestName = ''; //todo remove all mDesiredDestName
        $this->mLicense = $request->getText( 'wpLicense' );
        $this->mDestWarningAck = $request->getText( 'wpDestFileWarningAck' );
        $this->mWatchthis = $request->getBool( 'wpWatchthis' ) && $this->getUser()->isLoggedIn();
        $this->mCopyrightStatus = $request->getText( 'wpUploadCopyStatus' );
        $this->mCopyrightSource = $request->getText( 'wpUploadSource' );
        $this->mForReUpload = $request->getBool( 'wpForReUpload' ); // updating a file
        $this->mCancelUpload = $request->getCheck( 'wpCancelUpload' );
        $this->mUploadClicked = $this->getUploadClicked( $request );
        $this->mComment = $this->getComment( $request );

        $this->mIgnoreWarning = $request->getCheck( 'wpIgnoreWarning' )
            || $request->getCheck( 'wpUploadIgnoreWarning' );

        // If it was posted check for the token (no remote POST'ing with user credentials)
        $token = $request->getVal( 'wpEditToken' );
        $this->mTokenOk = $this->getUser()->matchEditToken( $token );
    }

    protected function getUploadClicked ( $request ) {
        if (
            $request->wasPosted() &&
            ( $request->getCheck( 'wpUpload' ) || $request->getCheck( 'wpUploadIgnoreWarning' ) )
        ) return true;

        return false;
    }

    protected function getComment ( $request, $commentDefault = null) {
        $commentMsg = wfMessage( 'upload-default-description' )->inContentLanguage();
        if ( !$this->mForReUpload && !$commentMsg->isDisabled() ) {
            $commentDefault = $commentMsg->plain();
        }

        return $request->getText( 'wpUploadDescription', $commentDefault );
    }

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

       if ( $this->checkToken() ) { //todo document
           $form->addPreText( $this->msg( 'session_fail_preview' )->parse() );
       }
        // Add upload error message if set
        $form->addPreText( $message );

        return $form;
    }

    //todo document
    protected function checkToken() {
        if ( !$this->mTokenOk && !$this->mCancelUpload && ( $this->mUpload && $this->mUploadClicked ) ) {
            return true;
        }
        return false;
    }

    //todo document
    protected function recursiveUpload() {
        $request = $this->mRequest;

        for( $i = 0 ; $i < $this->isMultipleUpload() ; $i++ ) {
            $request->key = $i;
            $this->mUpload = UploadBase::createFromRequest( $request );
            $this->processUpload();
        }
    }

}