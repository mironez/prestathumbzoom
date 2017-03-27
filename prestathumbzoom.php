<?php

if (!defined('_PS_VERSION_'))
	exit;

class PrestaThumbZoom extends Module
{
	private $html = '';

	public function __construct()
	{
		$this->name = 'prestathumbzoom';
		$this->tab = 'administration';
		$this->version = '1.0.0';
		$this->author = 'Miron Cegiela';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('Zoom Thumbs');
		$this->description = $this->l('Product thumbnails enlarge on cursor hover.');
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
	}

	public function install()
	{
		$this->removeCachedImages();
		return (parent::install() && $this->registerHook('backOfficeFooter'));
		return true;

	}  


	public function hookBackOfficeFooter()
	{
		$html = '<script type="text/javascript">
					$(".imgm").css("width", "55px");
					$( ".imgm" ).hover(
					  function() {
						$( this ).css("width", "155px");
					  }, function() {
						$( this ).css("width", "55px");
					  }
					);
				</script>';

		return $html;
	}
	
	public function uninstall()
	{
	  if (!parent::uninstall() ||
		!$this->removeCachedImages()
	  )
		return false;
	 
	  return true;
	}
	
	public function removeCachedImages() {
		array_map('unlink', glob(_PS_TMP_IMG_DIR_."/product_mini_*"));
		return true;
	}
}
