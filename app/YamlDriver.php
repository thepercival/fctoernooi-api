<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 11-4-19
 * Time: 11:57
 */

namespace App;

use Symfony\Component\Yaml\Yaml;
use Doctrine\ORM\Mapping\Driver\YamlDriver as DoctrineYamlDriver;

class YamlDriver extends DoctrineYamlDriver
{
    protected function loadMappingFile($file)
    {
        return Yaml::parse(file_get_contents($file), Yaml::PARSE_CONSTANT);
    }
}