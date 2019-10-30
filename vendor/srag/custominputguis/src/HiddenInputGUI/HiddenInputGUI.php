<?php

namespace srag\CustomInputGUIs\SrFilePatcher\HiddenInputGUI;

use ilHiddenInputGUI;
use srag\DIC\SrFilePatcher\DICTrait;

/**
 * Class HiddenInputGUI
 *
 * @package srag\CustomInputGUIs\SrFilePatcher\HiddenInputGUI
 *
 * @author  studer + raimann ag - Team Custom 1 <support-custom1@studer-raimann.ch>
 */
class HiddenInputGUI extends ilHiddenInputGUI {

	use DICTrait;


	/**
	 * HiddenInputGUI constructor
	 *
	 * @param string $a_postvar
	 */
	public function __construct(/*string*/
		$a_postvar = "") {
		parent::__construct($a_postvar);
	}
}
