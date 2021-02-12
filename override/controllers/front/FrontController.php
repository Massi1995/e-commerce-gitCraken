<?php


use PrestaShop\PrestaShop\Adapter\ServiceLocator;

class FrontController extends FrontControllerCore
{
    private function isSameAsOriginalPassword($firstname,$lastname, $actualPassword)
    {
        $mdp =str_replace(' ', '',strtoupper($firstname.$lastname));
          return password_verify($mdp, $actualPassword);

    }

    public function init()
    {
        if (isset($_GET['logout']) || ($this->context->customer->logged && Customer::isBanned($this->context->customer->id))){
            $this->context->customer->logout();
            Tools::redirect('index.php?controller=authentication?back=index');
        } elseif (isset($_GET['mylogout'])) {
            $this->context->customer->mylogout();
            Tools::redirect('index.php?controller=authentication?back=index');
        }

        parent::init();

        if ($this->context->customer->isLogged()) {

            if ($this->isSameAsOriginalPassword($this->context->customer->firstname,$this->context->customer->lastname, $this->context->customer->passwd) && $this->php_self != 'password' && $this->php_self != 'authentication') {
              Tools::redirect('index.php?controller=password?back=index');

            }
        } elseif (!$this->context->customer->isLogged() && $this->php_self != 'authentication' && $this->php_self != 'password') {
                Tools::redirect('index.php?controller=authentication?back=index');
        }
    }
}