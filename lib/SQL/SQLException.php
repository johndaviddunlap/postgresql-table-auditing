<?php
/**
 * BSD 3 Clause License
 * Copyright (c) 2017, John Dunlap<john.david.dunlap@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *    - Redistributions of source code must retain the above copyright notice, this
 *      list of conditions and the following disclaimer.
 *    - Redistributions in binary form must reproduce the above copyright notice,
 *      this list of conditions and the following disclaimer in the documentation
 *      and/or other materials provided with the distribution.
 *    - Neither the name of the copyright holder nor the names of its contributors may
 *      be used to endorse or promote products derived from this software without 
 *      specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY 
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES 
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT 
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT 
 * OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR 
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace lib\SQL;


class SQLException extends \Exception {
    public $__pretty;

    public function __construct($message, $code = 0 ) {
        // make sure everything is assigned properly
        parent::__construct($message, $code);
    }

    public function __toString()
    {
        if( isset( $this->__pretty ) && is_string($this->__pretty ) ) {
            return $this->__pretty .": Error Code: [{$this->code}]\n";
        } else {
            return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
        }
    }

    public function setPretty($str) {
        if( !isset($str) ) { return; }
        if( !is_string($str) ) {
            throw new \Exception("Excepted parameter to be a string but got (".gettype($str).") instead");
        }
        $this->__pretty = $str;
    }

}