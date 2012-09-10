<?php

namespace Rex\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use Rex\Data\Database;
use Rex\Data\TransactionPDO;
use PDO;

class SymfonyBundle extends Bundle {
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

    }

    function boot() {
      $database = $this->container->getParameter("database_name");
      $server = $this->container->getParameter("database_host");
      $username = $this->container->getParameter("database_user");
      $password = $this->container->getParameter("database_password");

      $db = new TransactionPDO("mysql:dbname=$database;host=$server", $username, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8" ));

      Database::setDB($db);

      \Rex\Log::setLogger($this->get("logger"));
    }
}

