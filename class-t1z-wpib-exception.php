<?php
/**
* Définition d'une classe d'exception personnalisée
*/
class T1z_WPIB_Exception extends Exception
{
  const FILES = 0;
  const MYSQL = 1;
  const ZIP = 2;
  // Redéfinissez l'exception ainsi le message n'est pas facultatif
  public function __construct($message, $code, Exception $previous = null) {

    // traitement personnalisé que vous voulez réaliser ...

    // assurez-vous que tout a été assigné proprement
    parent::__construct($message, $code, $previous);
  }

  // chaîne personnalisée représentant l'objet
  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }

  public function getType() {
    $types = [
      T1z_WPIB_Exception::FILES => 'Files',
      T1z_WPIB_Exception::MYSQL => 'MySQL',
      T1z_WPIB_Exception::ZIP => 'Zip'
    ];
    return $types[$this->code];
  }
}