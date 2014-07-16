<?php

class MultipleUploadForm extends HTMLForm {
    protected $mWatch; //todo clean unnecessary properties from here and constructor
    protected $mForReUpload;
    protected $mSessionKey;
    protected $mHideIgnoreWarning;
    protected $mDestWarningAck;
    protected $mDestFile;

    protected $mComment;
    protected $mTextTop;
    protected $mTextAfterSummary;

    protected $mSourceIds;

    protected $mMaxFileSize = array();
    protected $mMaxUploadSize = array();

    public function __construct( array $options = array(), IContextSource $context = null ) {
        $this->mWatch = !empty( $options['watch'] );
        $this->mForReUpload = !empty( $options['forreupload'] );
        $this->mHideIgnoreWarning = !empty( $options['hideignorewarning'] );
        $this->mDestWarningAck = !empty( $options['destwarningack'] );

        $this->mSessionKey = isset( $options['sessionkey'] ) ? $options['sessionkey'] : '';
        $this->mDestFile = isset( $options['destfile'] ) ? $options['destfile'] : '';
        $this->mComment = isset( $options['description'] ) ? $options['description'] : '';
        $this->mTextTop = isset( $options['texttop'] ) ? $options['texttop'] : '';
        $this->mTextAfterSummary = isset( $options['textaftersummary'] ) ? $options['textaftersummary'] : '';

        // set the upload size limit
        $this->mMaxUploadSize['file'] = $this->getMaxUploadSize(); //todo also fix this to MultipleFile?

        //set the descriptor
        $descriptor = $this->getSourceSection() + $this->getDescriptionSection() + $this->getOptionsSection();

        // in php, can't call the parent::__construct in two levels, so we have to do this:
        parent::__construct( $descriptor, $context, 'MultipleUpload' ); //todo check this

        # Set some form properties
        $this->setSubmitText( $this->msg( 'uploadbtn' )->text() );
        $this->setSubmitName( 'wpUpload' );
        # Used message keys: 'accesskey-upload', 'tooltip-upload'
        $this->setSubmitTooltip( 'upload' );
        $this->setId( 'mw-upload-form' ); //todo change?

        # Build a list of IDs for javascript insertion
        $this->mSourceIds = array();
        foreach ( $this->getSourceSection() as $field ) {
            if ( !empty( $field['id'] ) ) {
                $this->mSourceIds[] = $field['id'];
            }
        }
    }

    /*
     *
     */
    protected function getMaxUploadSize() {
        // Limit to upload_max_filesize unless we are running under HipHop and that setting doesn't exist
        if ( !wfIsHipHop() ) {
            $maxUpSize = max(
                UploadBase::getMaxUploadSize( 'file' ),
                wfShorthandToInteger( ini_get( 'upload_max_filesize' ) ),
                wfShorthandToInteger( ini_get( 'post_max_size' ) )
            );
            return $maxUpSize;
        }
        return false;
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
        // return now if we've already been submitted todo document better
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

        //build the descriptor
        $descriptor = array();

        $descriptor['UploadMultipleFile'] = array(
            'class' => 'UploadMultipleSourceField',
            'section' => 'source',
            'type' => 'file', // Class is defined, but put this here so HTMLForm constructor gives us enc='multipart/..'
            'id' => 'wpUploadMultipleFile',
            'label-message' => 'sourcefilename',
            'upload-type' => 'MultipleFile',
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
        $descriptor['UploadDescription'] = array(
            'type' => 'textarea',
            'section' => 'description',
            'id' => 'wpUploadDescription',
            'label-message' => $this->mForReUpload ? 'filereuploadsummary' : 'fileuploadsummary',
            'default' => $this->mComment,
            'cols' => $this->getUser()->getIntOption( 'cols' ),
            'rows' => 8,
        );

        $descriptor['EditTools'] = array(
            'type' => 'edittools',
            'section' => 'description',
            'message' => 'edittools-upload',
        );

        $descriptor['License'] = array(
            'type' => 'select',
            'class' => 'Licenses',
            'section' => 'description',
            'id' => 'wpLicense',
            'label-message' => 'license',
        );

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

    /**
     * Get the descriptor of the fieldset that contains the upload options,
     * such as "watch this file". The section is 'options'
     *
     * @return Array: descriptor array
     */
    protected function getOptionsSection() { //todo slash
        $user = $this->getUser();
        if ( $user->isLoggedIn() ) {
            $descriptor = array(
                'Watchthis' => array(
                    'type' => 'check',
                    'id' => 'wpWatchthis',
                    'label-message' => 'watchthisupload',
                    'section' => 'options',
                    'default' => $user->getOption( 'watchcreations' ),
                )
            );
        }
        if ( !$this->mHideIgnoreWarning ) {
            $descriptor['IgnoreWarning'] = array(
                'type' => 'check',
                'id' => 'wpIgnoreWarning',
                'label-message' => 'ignorewarnings',
                'section' => 'options',
            );
        }

        $descriptor['DestFileWarningAck'] = array(
            'type' => 'hidden',
            'id' => 'wpDestFileWarningAck',
            'default' => $this->mDestWarningAck ? '1' : '',
        );

        if ( $this->mForReUpload ) {
            $descriptor['ForReUpload'] = array(
                'type' => 'hidden',
                'id' => 'wpForReUpload',
                'default' => '1',
            );
        }

        return $descriptor;
    }

    /**
     * Get the messages indicating which extensions are preferred and prohibitted.
     *
     * @return String: HTML string containing the message
     */
    protected function getExtensionsMessage() { //todo slash this -- REALLY refactor so it's my own
        # Print a list of allowed file extensions, if so configured.  We ignore
        # MIME type here, it's incomprehensible to most people and too long.
        global $wgCheckFileExtensions, $wgStrictFileExtensions,
               $wgFileExtensions, $wgFileBlacklist;

        if ( $wgCheckFileExtensions ) {
            if ( $wgStrictFileExtensions ) {
                # Everything not permitted is banned
                $extensionsList =
                    '<div id="mw-upload-permitted">' .
                    $this->msg( 'upload-permitted', $this->getContext()->getLanguage()->commaList( array_unique( $wgFileExtensions ) ) )->parseAsBlock() .
                    "</div>\n";
            } else {
                # We have to list both preferred and prohibited
                $extensionsList =
                    '<div id="mw-upload-preferred">' .
                    $this->msg( 'upload-preferred', $this->getContext()->getLanguage()->commaList( array_unique( $wgFileExtensions ) ) )->parseAsBlock() .
                    "</div>\n" .
                    '<div id="mw-upload-prohibited">' .
                    $this->msg( 'upload-prohibited', $this->getContext()->getLanguage()->commaList( array_unique( $wgFileBlacklist ) ) )->parseAsBlock() .
                    "</div>\n";
            }
        } else {
            # Everything is permitted.
            $extensionsList = '';
        }
        return $extensionsList;
    }

    /**
     * Empty function; submission is handled elsewhere. //todo ??
     *
     * @return bool false
     */
    function trySubmit() {
        return false;
    }

}