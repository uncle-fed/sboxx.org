<?php

$db = '/var/www/html/dbdat.zip';
$dd = file_exists($db) ? date('d.m.Y', filemtime($db)) : "";

?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <title>Спутниковые ресиверы Cuberevo / IPBox / Sezam : прошивки PGI, скрипты, поддержка</title>
    <link rel="stylesheet" href="index.css" />
</head>
<body>
    <div id="logo"><img src="sputnik.jpg" alt="Sputnik"></div>
    <header>
        <h1>Добро пожаловать на сайт проекта PGI для IPBOX HD!</h1>
        <p>Здесь вы сможете найти полезные приложения, скрипты и ссылки для вашего устаревшего ресивера Cuberevo/IPBox/Sezam HD.</p>
        <p>ВНИМАНИЕ! Большинство материалов этого и других упомянутых сайтов касается работы с <strong>прошивкой PGI Final</strong>!</p>
    </header>
    <section>
        <ul>
<?php if ($dd) { ?>
            <li><a href="dbdat.zip?<?=$dd?>">Чистая база данных db.dat</a> с обновленным списком спутников и транспондеров<?=$dd ? " от $dd" : ""?></li>
<?php } ?>
            <li><a href="dbdat.txt?<?=date('d.m.Y')?>">История изменений базы данных db.dat</a> обновляется вместе с базой ежедневно в 00:00 <?=date('T')?> </li>
            <li><a href="/epg/">Программа передач</a> из Интернета для прошивок PGI - интерфейс для настройки</li>
            <li><a href="epg/pgi_epg.zip">Скрипт получения ТВ Гида</a> из Интернета (используется после настройки через линк выше)</li>
            <li><a href="piconid.zip">Скрипт идентификации пиконов</a>, нужен для того чтобы правильно назвать файлы с пиконами для прошивки PGI</li>
            <li><a href="multiboot/">Файлы конфигурации мультибута</a>, которые также идут в комплекте с прошивкой PGI</li>
        </ul>
        <p>Полезные ссылки на другие ресурсы, рекомендуемые к использованию:</p>
        <ul>
            <li><a title="https://mega.nz/#F!8BVVXCYB!RJq0aUtK_eQZGxoSZdA3Dw" href="https://mega.nz/#F!8BVVXCYB!RJq0aUtK_eQZGxoSZdA3Dw">Прошивки PGI</a> для всех поддерживаемых моделей ресиверов</li>
            <li>Домашняя страница программы для редактирования базы каналов <a href="http://www.pceditor.de">PC Editor</a>. Также, версия 1.2.60 доступна для скачивания <a href="Setup_PCEditor_1.2.60.zip">здесь</a>.</li>
            <li>Русскоязычный <a href="http://gomel-sat.bz/forum/21-ipbox-hd-sezam-hd-cuberevo-hd/">интернет-форум Gomel-Sat</a>, где тусуются пользователи ресиверов Cuberevo (и не только).</li>
        </ul>
    </section>
    <footer>
        Последнее обновление сайта: 18 июня 2017
    </footer>
</body>
</html>
