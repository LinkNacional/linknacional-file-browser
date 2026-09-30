<?php

namespace LinkNacional\Filebrowser\Includes;

class LinkNacionalFilebrowserDeactivator {

	public static function deactivate() {
		LinkNacionalFilebrowserFiles::clear_cleanup();
	}
}
