<?php
include("includes/conexion.php");

$usuarios = [
    ["usuario" => "admin",        "password" => "admin123", "rol" => "Administrador"],
    ["usuario" => "subdireccion", "password" => "sub123",   "rol" => "Subdirección"],
    ["usuario" => "instructor",   "password" => "inst123",  "rol" => "Instructor"],
    ["usuario" => "Guarda de Seguridad",      "password" => "cel123",   "rol" => "Guarda de Seguridad"]
];

foreach ($usuarios as $u) {

    $check = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
    $check->bind_param("s", $u['usuario']);
    $check->execute();
    $check->store_result();

    if ($check->num_rows == 0) {
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);

        $stmt = $conexion->prepare(
            "INSERT INTO usuarios (usuario, password, rol) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $u['usuario'], $hash, $u['rol']);
        $stmt->execute();

        echo "Usuario {$u['usuario']} insertado ✅<br>";
    } else {
        echo "Usuario {$u['usuario']} ya existe ⚠️<br>";
    }
}
