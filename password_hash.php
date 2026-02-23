<?php
$usuarios = [
    ["usuario" => "admin",        "password" => "ad", "rol" => "Administrador"],
    ["usuario" => "subdireccion", "password" => "sub",   "rol" => "Subdirección"],
    ["usuario" => "instructor",   "password" => "in",  "rol" => "Instructor"],
    ["usuario" => "Guarda de Seguridad",      "password" => "cel123",   "rol" => "Guarda de Seguridad"]
];

foreach ($usuarios as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);

    echo "INSERT INTO usuarios (usuario, password, rol) 
          VALUES ('{$u['usuario']}', '$hash', '{$u['rol']}');<br>";
}
?>
