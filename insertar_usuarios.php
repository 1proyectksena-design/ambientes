<?php
include("includes/conexion.php");

$usuarios = [
    ["usuario" => "admin",        "password" => "admin123", "rol" => "Administrador"],
    ["usuario" => "subdireccion", "password" => "sub123",   "rol" => "Subdirección"],
    ["usuario" => "instructor",   "password" => "inst123",  "rol" => "Instructor"],
    ["usuario" => "Guarda de Seguridad",      "password" => "cel123",   "rol" => "Guarda de Seguridad"]
];

foreach ($usuarios as $u) {

    // ¿Existe el usuario?
    $check = $conexion->prepare(
        "SELECT id_usuario FROM usuarios WHERE usuario = ?"
    );
    $check->bind_param("s", $u['usuario']);
    $check->execute();
    $check->store_result();

    if ($check->num_rows == 0) {

        // No existe → insertar
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);

        $stmt = $conexion->prepare(
            "INSERT INTO usuarios (usuario, password, rol)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $u['usuario'], $hash, $u['rol']);
        $stmt->execute();

        echo "Usuario {$u['usuario']} insertado ✅<br>";

    } else {

        // Existe → asegurar rol correcto
        $update = $conexion->prepare(
            "UPDATE usuarios SET rol = ? WHERE usuario = ?"
        );
        $update->bind_param("ss", $u['rol'], $u['usuario']);
        $update->execute();

        echo "Rol de {$u['usuario']} actualizado 🔄<br>";
    }
}

echo "<br>✔️ Proceso completado";
