<?php

use ILIAS\DI\Container;

/**
 * Class xonoAbstractGUI
 * @author Theodor Truffer <theo@fluxlabs.ch>
 */
abstract class xonoAbstractGUI
{
    protected Container $dic;
    protected ilOnlyOfficePlugin $plugin;

    /**
     * xoofAbstractGUI constructor.
     * @param Container          $dic
     * @param ilOnlyOfficePlugin $plugin
     */
    public function __construct(Container $dic, ilOnlyOfficePlugin $plugin)
    {
        $this->dic = $dic;
        $this->plugin = $plugin;
    }

}