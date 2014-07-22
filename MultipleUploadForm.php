<?php

class MultipleUploadForm extends HTMLForm {
    protected $mDestFile;
    protected $mMaxFileSize = array();
    protected $mMaxUploadSize = array();

    protected $mTextTop;
    protected $mTextAfterSummary;

    protected $mSourceIds;

    public function __construct( array $options = array(), IContextSource $context = null ) {
        $this->mWatch = !empty( $options['watch'] );
        $this->mDestFile = isset( $options['destfile'] ) ? $options['destfile'] : ''; //todo what is this?
        $this->mComment = isset( $options['description'] ) ? $options['description'] : '';
        $this->mTextTop = isset( $options['texttop'] ) ? $options['texttop'] : '';
        $this->mTextAfterSummary = isset( $options['textaftersummary'] ) ? $options['textaftersummary'] : '';

        // set the upload size limit
        $this->mMaxUploadSize['file'] = $this->getMaxUploadSize(); //todo also fix this to MultipleFile?

        //set the descriptor
        $descriptor = $this->getSourceSection() + $this->getDescriptionSection();

        parent::__construct( $descriptor, $context, 'MultipleUpload' );

        # Set some form properties
        $this->setSubmitText( $this->msg( 'uploadbtn' )->text() );
        $this->setSubmitName( 'wpMultipleUpload' );
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
            $maxUpSize = max(
                UploadBase::getMaxUploadSize( 'file' ),
                wfShorthandToInteger( ini_get( 'upload_max_filesize' ) ),
                wfShorthandToInteger( ini_get( 'post_max_size' ) )
            );
            return $maxUpSize;
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
        $descriptor = array();

        // set the upload type--this is used by UploadBase::CreateFromRequest to choose the handler, etc.
        $descriptor['SourceType'] = array(
            'type' => 'hidden',
            'default' => 'MultipleFile',
        );

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
            'label-message' => 'fileuploadsummary',
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
     * Get the messages indicating which extensions are preferred and prohibitted.
     *
     * @return String: HTML string containing the message
     */
    protected function getExtensionsMessage() { // todo slash this?
        # Print a list of allowed file extensions, if so configured.  We ignore
        # MIME type here, it's incomprehensible to most people and too long.
        global $wgCheckFileExtensions, $wgStrictFileExtensions,
               $wgFileExtensions, $wgFileBlacklist;

        if ( $wgCheckFileExtensions ) {
            if ( $wgStrictFileExtensions ) {
                # Everything not permitted is banned
                $extensionsList =
                    '<div id="mw-upload-permitted">' .
                        $this->msg( 'upload-permitted',
                            $this->getContext()->getLanguage()->commaList(
                                array_unique( $wgFileExtensions )
                            )
                        )->parseAsBlock() .
                    "</div>\n";
            } else {
                # We have to list both preferred and prohibited
                $extensionsList =
                    '<div id="mw-upload-preferred">' .
                        $this->msg( 'upload-preferred',
                            $this->getContext()->getLanguage()->commaList(
                                array_unique( $wgFileExtensions )
                            )
                        )->parseAsBlock() .
                    "</div>\n" .
                    '<div id="mw-upload-prohibited">' .
                        $this->msg( 'upload-prohibited',
                            $this->getContext()->getLanguage()->commaList(
                                array_unique( $wgFileBlacklist )
                            )
                        )->parseAsBlock() .
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