# Утилиты для работы с расписанием колледжа
Утилиты для манипуляций с расписанием Белгородского строительного колледжа, которое находится по адресу: http://www.belsk.ru/p12321aa3.html

Доступные функции:
* Представление информации о занятиях для выбранной учебной группы, с возможностью скачать в формате PDF.

## Демо
* http://d66237p1.beget.tech/
* http://school-schedule.byethost7.com/

## Требования
* PHP >= 7.4
* Apache 2.4

## Установка
1. Склонируйте репозиторий и выполните:
```bash
$ composer install
```
## Архитектура
Архитектура приложения построена на основе каркаса [NewEXE/single-entry-point-php](https://github.com/NewEXE/single-entry-point-php).

### Как создать страницу
1. Создать PHP-файл страницы в директории `src/pages` (допускается создание в субдиректории)
2. Добавить роут в `src/Config/routes.php`

### Как добавить и запустить консольный (CLI) скрипт
#### Создать:
1. Создать PHP-файл скрипта в директории `src/console/scripts` (допускается создание в субдиректории)
#### Запустить:
К примеру, скрипт находится по пути `src/console/scripts/path/to/script.php`:
```
php public/index.php path/to/script.php
```
(расширение `.php` можно опустить)

### Как добавить опции в конфиг приложения
1. Добавить публичное свойство в класс `Src\Config\App`

### Как показать ошибку пользователю
Чтобы корректно завершить работу приложения, необходимо бросить исключение:
```php
use Src\Exceptions\TerminateException;

throw new TerminateException('Пользователь увидит это сообщение об ошибке');
```

## Утилиты
### Обновить актуальный список всех групп для страницы `select-schedule-file.php`:
```
php public/index.php group-list/generate.php
```
Получает все имена групп с Excel-файлов техникума и с файлов примеров (находящихся в `public/samples`).
