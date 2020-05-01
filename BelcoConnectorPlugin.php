<?php 

//error vault
/* 
[Wed Mar 04 16:19:47.821836 2020] [proxy_fcgi:error] [pid 4454:tid 140525889783552] [client 5.159.33.2:50382] AH01071: Got error 'PHP message: PHP Fatal error:  Uncaught Error: Call to undefined method BelcoConnectorPlugin\\BelcoConnectorPlugin::subscribeEvent() in /var/www/vhosts/jdjictdev.nl/belco.jdjictdev.nl/custom/plugins/BelcoConnectorPlugin/BelcoConnectorPlugin.php:23\nStack trace:\n#0 /var/www/vhosts/jdjictdev.nl/belco.jdjictdev.nl/engine/Shopware/Bundle/PluginInstallerBundle/Service/PluginInstaller.php(161): BelcoConnectorPlugin\\BelcoConnectorPlugin->install(Object(Shopware\\Components\\Plugin\\Context\\InstallContext))\n#1 [internal function]: Shopware\\Bundle\\PluginInstallerBundle\\Service\\PluginInstaller->Shopware\\Bundle\\PluginInstallerBundle\\Service\\{closure}(Object(Shopware\\Components\\Model\\ModelManager))\n#2 /var/www/vhosts/jdjictdev.nl/belco.jdjictdev.nl/vendor/doctrine/orm/lib/Doctrine/ORM/EntityManager.php(235): call_user_func(Object(Closure), Object(Shopware\\Components\\Model\\ModelManager))\n#3 /var/www/vhosts/jdjictdev.nl/belco.jdjictdev.nl/engine/Shopware/Bundle/PluginInstallerBundle/Service/PluginInstaller.php(169):...', referer: https://belco.jdjictdev.nl/backend/
*/

namespace BelcoConnectorPlugin;

use Shopware\Components\Plugin;

class BelcoConnectorPlugin extends Plugin {
    
}