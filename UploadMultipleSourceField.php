<?php

class UploadMultipleSourceField extends HTMLFormField{
    /*
     *
     */
    function getInputHTML( $value ) {
        $attribs = array(
                'id' => $this->mID,
                'name' => $this->mName . '[]', // html array
                'size' => $this->getSize(),
                'value' => $value,
                'type' => 'file',
                'multiple' => 'multiple',
            ) + $this->getTooltipAndAccessKey(); //todo ?

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