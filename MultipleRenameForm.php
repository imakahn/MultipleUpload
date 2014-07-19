<?php

class MultipleRenameForm extends HTMLForm {
    protected $recoverableFiles;
    public $mThumb;

    /*
     * todo just put some helpful text at the top of the form and call it a day
     */
    public function __construct( array $recoverableFiles, IContextSource $context = null ) {
        if ($recoverableFiles) {
            $this->recoverableFiles = $recoverableFiles;
        }

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

        foreach( $this->recoverableFiles as $file ) {
            $name = $file['name'];
            $message = $file['msg'];
            $stash = $file['file'];

            if ( isset( $stash ) ) {
                global $wgContLang;

                $thumb = $stash->transform( array( 'width' => 120 ) );
                $src = $thumb->getUrl();
            }

            $descriptor["Rename$num"] = array(
                'class' => 'MultipleRenameField',
//                'id' => $fileKey,
                'label' => htmlentities($name),
                'title' => $this->msg($message)->text(),
                'required' => true,
                'src' => isset( $src ) ? $src : ''
            );

            $num++;
        }

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
    function getLabelHTML() { //todo document
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
        $attribs = array(
                'id' => $this->mID,
                'name' => $this->mName,
                'size' => $this->getSize(),
                'value' => $value,
                'type' => 'text',
                'title' => $this->mParams['title'],
                'required' => true
            );

        return Html::element( 'input', $attribs );
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