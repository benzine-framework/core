<?php

namespace Benzine\Redis;
use Benzine\Redis\Lua;

class Redis extends \Redis
{
    /** @var Lua\LuaExtension[] */
    private array $scripts;
    
    public function initialiseExtensions() : void
    {
        $this->scripts[] = new Lua\SetIfHigher($this);
    }
    
    public function connect($host, $port = 6379, $timeout = 0.0, $reserved = null, $retryInterval = 0, $readTimeout = 0.0)
    {
        parent::connect($host, $port, $timeout, $reserved, $retryInterval, $readTimeout);
        $this->initialiseExtensions();
    }

    public function __call($name, $arguments)
    {
        foreach($this->scripts as $script){
            foreach($script->getFunctionNames() as $functionName){
                if(strtolower($name) == strtolower($functionName)){
                    return $this->evalSha($script->getHash(), $arguments);
                }
            }
        }
    }
}