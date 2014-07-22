<?php

class MultipleRenameForm extends HTMLForm {
    protected $mRecoverableFiles, $mGallery;
    public $mThumb;

    /*
     *
     */
    public function __construct( array $recoverableFiles, array $gallery, IContextSource $context = null ) {
        $this->mRecoverableFiles = $recoverableFiles;
        $this->mGallery = $gallery;

        $descriptor = $this->getDescriptor();
        parent::__construct( $descriptor, $context, 'rename'); // todo check 'rename' argument

        $this->setSubmitName( 'wpMultipleRename' );
        $this->getOutput()->setPageTitle( 'Rename Files' );
        $this->addPreText( 'The files below need to be renamed for submission.' );
    }

    /*
     *
     */
    protected function getDescriptor() {
        $descriptor = array();
        $num = 1;

        $descriptor['SourceType'] = array(
            'type' => 'hidden',
            'default' => 'MultipleStash',
        );

        foreach( $this->mRecoverableFiles as $file ) {
            list( $stash, $name, $message ) = array( $file['file'], $file['name'], $file['msg'] );
            $fileKey = $stash->getFileKey();

            if ( isset( $stash ) ) {
                $thumb = $stash->transform( array( 'width' => 120 ) );
                $src = $thumb->getUrl();
            }

            $descriptor["Rename$num"] = array(
                'class' => 'MultipleRenameField',
                'label' => htmlentities($name),
                'title' => 'Error reason: ' . $message,
                'required' => true,
                'src' => isset( $src ) ? $src : ''
            );

            $descriptor["RenameKey$num"] = array(
                'type' => 'hidden',
                'default' => $fileKey
            );

            $num++;
        }

        $descriptor['IgnoreWarning'] = array(
            'type' => 'check',
            'label' => 'Ignore Warnings'
        );

        // arrays etc we don't want to lose. interim until session is utilized. just the gallery currently
        $descriptor['Data'] =  array(
            'type' => 'hidden',
            'default' => serialize( $this->mGallery )
        );

        return $descriptor;
    }

    /**
     * Empty function; submission is handled elsewhere. //todo IF YOU USE IT, ACTUALLY DOCUMENT WHY
     *
     * @return bool false
     */
    function trySubmit() {
        return false;
    }

}

class MultipleRenameField extends HTMLFormField{
    /*
     *
     */
    function getLabelHTML() {
        $labelValue = trim( $this->getLabel() );

        $thumb = Html::rawElement( 'td',
            array( 'class' => 'thumbimage-rename' ) ,
            Html::element( 'img', array( 'src' => $this->mParams['src'], 'class' => 'thumbimage' ) )
        );

        $label = Html::rawElement( 'td',
            array( 'class' => 'mw-label-rename' ),
            Html::rawElement( 'label', array(), $labelValue )
        );

        return $thumb . $label;
    }

    /*
     *
     */
    function getInputHTML( $value ) {
        $inputAttribs = array(
            'id' => $this->mID,
            'name' => $this->mName,
            'size' => $this->getSize(),
            'value' => $value,
            'type' => 'text',
            'title' => $this->mParams['title'],
            'required' => true
        );

        $inputAttribs = Html::element( 'input', $inputAttribs );

        return $inputAttribs;
    }

    /**
     * @return int
     */
    function getSize() {
        return isset( $this->mParams['size'] )
            ? $this->mParams['size']
            : 20;
    }

}