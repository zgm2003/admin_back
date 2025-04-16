<?php

namespace app\module;

class BaseModule
{

  public function response($data = [], $msg = 'success', $code = 0)
  {
    return [$data,$code,$msg];
  }
}
