<?php 

namespace BelcoConnectorPlugin\Components;

class BelcoConnector {

    public function install() {

        $this->createConfig();

        return true;
    }
}