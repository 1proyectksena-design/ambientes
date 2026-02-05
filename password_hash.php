<?php
$usuarios = [
    ["usuario" => "admin",        "password" => "admin123", "rol" => "Administrador"],
    ["usuario" => "subdireccion", "password" => "sub123",   "rol" => "Subdirección"],
    ["usuario" => "instructor",   "password" => "inst123",  "rol" => "Instructor"],
    ["usuario" => "celador",      "password" => "cel123",   "rol" => "Celador"]
];

foreach ($usuarios as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);

    echo "INSERT INTO usuarios (usuario, password, rol) 
          VALUES ('{$u['usuario']}', '$hash', '{$u['rol']}');<br>";
}
?>
