<?php

use ILIAS\DI\Container;

abstract class xonoAbstractGUI
{
    protected Container $dic;
    protected ilOnlyOfficePlugin $plugin;

    public function __construct(Container $dic, ilOnlyOfficePlugin $plugin)
    {
        $this->dic = $dic;
        $this->plugin = $plugin;
    }

}
