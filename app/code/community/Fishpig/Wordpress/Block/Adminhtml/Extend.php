<?php
/**
 * @category    Fishpig
 * @package     Fishpig_Wordpress
 * @license     http://fishpig.co.uk/license.txt
 * @author      Ben Tideswell <help@fishpig.co.uk>
 */

 	// Include the FPAdmin Extend Block class
	require_once(Mage::getModuleDir('', 'Fishpig_Wordpress') . DS . implode(DS, array('FPAdmin', 'Block', 'Adminhtml', 'Extend.php')));

class Fishpig_Wordpress_Block_Adminhtml_Extend extends Fishpig_FPAdmin_Block_Adminhtml_Extend {}
