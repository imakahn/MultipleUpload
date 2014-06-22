<?php
/**
 * Form for handling uploads and special page.
 *
 * @ingroup SpecialPage
 * @ingroup Upload
 */
class SpecialMyUpload extends SpecialUpload {

    /**
     * Constructor : initialise object
     */
    public function __construct( ) {
        SpecialPage::__construct( 'MyUpload', '', true );//todo change to SpecialMultipleUpload
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
        if ( $this->mTokenOk && !$this->mCancelUpload && ( $this->mUploadClicked ) ) { // todo refactor

            if ( $this->isMultipleUpload() )
            {
                $this->recursiveUpload();
            }
            else {
                $this->mUpload = UploadBase::createFromRequest( $this->mRequest );
                $this->processUpload();
            }
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

        // user checks
        $user = $this->getUser();
        $permissionRequired = UploadBase::isAllowed( $user );
        if ( $permissionRequired !== true ) {
            throw new PermissionsError( $permissionRequired );
        }
        if ( $user->isBlocked() ) {
            throw new UserBlockedError( $user->getBlock() );
        }

        $this->checkReadOnly();
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

    protected function getComment ( $request ) {
        $commentDefault = '';

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

        for($i = 0 ; $i < $this->isMultipleUpload() ; $i++) {
            $request->key = $i;

            $this->mUpload = UploadBase::createFromRequest( $request );
            $this->processUpload();
        }
    }

}

class MultipleUploadForm extends UploadForm{

    public function __construct( array $options = array(), IContextSource $context = null ) {
        parent::__construct();

        $this->getMaxUploadSize();
    }

    protected function getMaxUploadSize() {
        // Limit to upload_max_filesize unless we are running under HipHop and that setting doesn't exist
        if ( !wfIsHipHop() ) {
            $this->mMaxUploadSize['file'] = min(
                UploadBase::getMaxUploadSize( 'file' ),
                wfShorthandToInteger( ini_get( 'upload_max_filesize' ) ),
                wfShorthandToInteger( ini_get( 'post_max_size' ) )
            );
        }
    }

    /**
     * Get the descriptor of the fieldset that contains the file source
     * selection. The section is 'source'
     *
     * Called by constructor
     *
     * @return Array: descriptor array
     */
    protected function getSourceSection() {
        if ( $this->mSessionKey ) {
            return array(
                'SessionKey' => array(
                    'type' => 'hidden',
                    'default' => $this->mSessionKey,
                ),
                'SourceType' => array(
                    'type' => 'hidden',
                    'default' => 'Stash',
                ),
            );
        }

        $descriptor = array();
        $descriptor['UploadFile'] = array(
            'class' => 'UploadMultipleSourceField',
            'section' => 'source',
            'type' => 'file', // Class is defined, but put this here so HTMLForm constructor gives us enc='multipart/..'
            'id' => 'wpUploadFile',
            'label-message' => 'sourcefilename',
            'upload-type' => 'File',
            'help' => $this->msg( 'upload-maxfilesize',
                    $this->getContext()->getLanguage()->formatSize( $this->mMaxUploadSize['file'] ) )->parse() .
                        $this->msg( 'word-separator' )->escaped() . $this->msg( 'upload_source_file' )->escaped(),
        );

        $descriptor['Extensions'] = array(
            'type' => 'info',
            'section' => 'source',
            'default' => $this->getExtensionsMessage(),
            'raw' => true,
        );

        return $descriptor;
    }

    /**
     * Get the descriptor of the fieldset that contains the file description
     * input. The section is 'description'
     *
     * Called by constructor
     *
     * @return Array: descriptor array
     */
    protected function getDescriptionSection() {
        $descriptor = array(
            'UploadDescription' => array(
                'type' => 'textarea',
                'section' => 'description',
                'id' => 'wpUploadDescription',
                'label-message' => $this->mForReUpload
                        ? 'filereuploadsummary'
                        : 'fileuploadsummary',
                'default' => $this->mComment,
                'cols' => $this->getUser()->getIntOption( 'cols' ),
                'rows' => 8,
            )
        );
        if ( $this->mTextAfterSummary ) {
            $descriptor['UploadFormTextAfterSummary'] = array(
                'type' => 'info',
                'section' => 'description',
                'default' => $this->mTextAfterSummary,
                'raw' => true,
            );
        }

        $descriptor += array(
            'EditTools' => array(
                'type' => 'edittools',
                'section' => 'description',
                'message' => 'edittools-upload',
            )
        );

        if ( $this->mForReUpload ) {
            $descriptor['DestFile']['readonly'] = true;
        } else {
            $descriptor['License'] = array(
                'type' => 'select',
                'class' => 'Licenses',
                'section' => 'description',
                'id' => 'wpLicense',
                'label-message' => 'license',
            );
        }

        global $wgUseCopyrightUpload;
        if ( $wgUseCopyrightUpload ) {
            $descriptor['UploadCopyStatus'] = array(
                'type' => 'text',
                'section' => 'description',
                'id' => 'wpUploadCopyStatus',
                'label-message' => 'filestatus',
            );
            $descriptor['UploadSource'] = array(
                'type' => 'text',
                'section' => 'description',
                'id' => 'wpUploadSource',
                'label-message' => 'filesource',
            );
        }

        return $descriptor;
    }

}

class UploadMultipleSourceField extends HTMLFormField{

    function getInputHTML( $value ) {
        $attribs = array(
                'id' => $this->mID,
                'name' => $this->mName . '[]', // html array
                'size' => $this->getSize(),
                'value' => $value,
                'type' => 'file',
                'multiple' => 'multiple',
            ) + $this->getTooltipAndAccessKey();

        if ( $this->mClass !== '' ) {
            $attribs['class'] = $this->mClass;
        }

        if ( !empty( $this->mParams['disabled'] ) ) {
            $attribs['disabled'] = 'disabled';
        }

        return Html::element( 'input', $attribs );
    }

    /**
     * @return int
     */
    function getSize() {
        return isset( $this->mParams['size'] )
            ? $this->mParams['size']
            : 60;
    }

}

class WebMultipleRequest extends WebRequest{

    public $key = null;

    /**
     * Return a WebRequestUpload object corresponding to the key
     *
     * @param $uploadID string
     * @return WebRequestUpload
     */
    public function getUpload( $uploadID ) {
        if ( !is_null( $this->key ) ){
            return new WebMultipleRequestUpload( $this, $uploadID, $this->key );
        }

        return new WebMultipleRequestUpload( $this, $uploadID );
    }

}

class WebMultipleRequestUpload extends WebRequestUpload{

    /**
     * Constructor. Should only be called by WebMultipleRequest
     *
     * @param $request WebRequest The associated request
     * @param string $uploadID ID in $_FILES array (name of form field)
     * @param int $key If we're a batch upload, $key enables us to run recursively by returning scalar
     *        values that the rest of the relevant classes/methods expect (as opposed to arrays)
     */
    public function __construct( $request, $uploadID, $key = null ) {
        $this->request = $request;
        $this->doesExist = isset( $_FILES[$uploadID] );
        $this->uploadID = $uploadID;

        if ( $this->doesExist ) {

            if ( !is_null( $key ) ) {

                // 'name' == name of key, 'array' == array it holds
                // 'id' == numeric keys in array, 'value' == values
                foreach ( $_FILES[$uploadID] as $name => $array) {
                    foreach ( $array as $id => $value ) {
                        if ( $id === $key ) {
                            $this->fileInfo[$name] = $value;
                        }
                    }
                }
            } else {
                $this->fileInfo = $_FILES[$uploadID];
            }

        }
    }

}
