<?php
// Подключение к базе данных
$servername = "localhost";
$username = "prukon_test.fcistok.kindslink";
$password = "X]:Olt{Yf3!;O@Rz";
$dbname = "prukon_test.fcistok.kindslink";


$conn = new mysqli($servername, $username, $password, $dbname);

// Проверка соединения
if ($conn->connect_error) {
    die("Ошибка соединения: " . $conn->connect_error);
}

// Получение данных из POST-запроса
$imageData = $_POST['image'];


//Обновление аватарки у юзера
// Преобразование base64 в бинарный формат
list($type, $imageData) = explode(';', $imageData);
list(, $imageData)      = explode(',', $imageData);
$imageData = base64_decode($imageData);

// Уникальное имя файла
$imageName = uniqid() . '.png';

// Путь для сохранения изображения
$imagePath = 'img/' . $imageName;

// Сохранение изображения на сервере
file_put_contents($imagePath, $imageData);

// Обновление записи в базе данных
// Предполагаем, что у вас есть идентификатор записи, которую нужно обновить
$id = 1; // Замените на реальный идентификатор

//$sql = "UPDATE your_table_name SET image_path='$imagePath' WHERE id=$id";
$sql = "UPDATE `users` SET `image` = '$imagePath' WHERE `users`.`id` = 1";




if ($conn->query($sql) === TRUE) {
    echo "Запись успешно обновлена";
} else {
    echo "Ошибка обновления записи: " . $conn->error;
}
echo "Image saved at: " . $imagePath;

$conn->close();
?>
