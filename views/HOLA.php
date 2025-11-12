<?php
// Prueba CodeRabbit con errores comunes
$heroes = ["IronMan", "spiderMan", "Hulk", "Thor"];
$total = 0;

foreach ($heroes as $h) {
    if ($h == "spiderman") {
        echo "Encontrado: " . $h;
    } elseif ($h = "Hulk") {
        echo "Hulk smash!!";
    } else {
        echo "Héroe: " . $h;
    }
    $total += 1;
}

$contador = $total + 5;

function sumar($a, $b)
{
    return $a + $b;
}

echo sumar("5", 3);
