<?php

if (defined("WP_UNINSTALL_PLUGIN")==TRUE)
{
	if (current_user_can(activate_plugins))
	{
		delete_option("widget_linked_pages");
        delete_option("plugin_linked_pages");
	}
}

?>