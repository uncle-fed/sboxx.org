<?php

$db = './dbdat.zip';
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
    <aside><a title="PGI Archive" href="https://mega.nz/#F!kKgzDAhJ!kZTL3xQcEgz_8fqLYC2fAA"><img src="sputnik.jpg" alt="Sputnik"></a></aside>
    <main>
    <header>
        <h1>Добро пожаловать на сайт проекта PGI для IPBOX HD!</h1>
        <p>Здесь вы сможете найти полезные приложения, скрипты и ссылки для вашего устаревшего ресивера Cuberevo/IPBox/Sezam HD.</p>
        <p>ВНИМАНИЕ! Большинство материалов этого и других упомянутых сайтов касается работы с <strong>прошивкой PGI Final</strong>!</p>
    </header>
    <section>
        <h2>База данных db.dat (спутники и транспондеры):</h2>
        <ul>
            <li><a href="dbdat.txt">История изменений базы данных db.dat</a> обновляется вместе с базой ежедневно в 00:00 UTC</li>
            <li><a href="dbdat.zip">Чистая база данных db.dat</a> с обновленным списком спутников и транспондеров<?=$dd ? " от $dd" : ""?></li>
        </ul>
    </section>
    <section>
        <h2>Программа передач для прошивок PGI на основе XMLTV</h2>
        <ul>
            <li><a href="/epg/">Веб интерфейс для начальной настройки</a></li>
            <li><a href="https://mega.nz/#!pO43VZqA!HuSn4IJEDx-ERETfTwmA0fllJx0-8_WI5tfPk4gi_Uc">Скрипт для обновления данных EPG для прошивки PGI</a></li>
        </ul>
    </section>
    <section>
        <h2>Полезные ссылки на другие ресурсы, рекомендуемые к использованию:</h2>
        <ul>
            <li><a title="https://mega.nz/#F!kKgzDAhJ!kZTL3xQcEgz_8fqLYC2fAA" href="https://mega.nz/#F!kKgzDAhJ!kZTL3xQcEgz_8fqLYC2fAA">Файловый архив PGI</a> &mdash; прошивки PGI, файлы конфигурации мультибут и скрипты для EPG и пиконов.</li>
            <li>Домашняя страница программы для редактирования базы каналов <a href="http://www.pceditor.de">PC Editor</a>.<br /> Также, версия 1.2.60 доступна для скачивания из файлового репозитория по ссылке выше.</li>
            <li>Русскоязычный <a href="http://gomel-sat.bz/forum/21-ipbox-hd-sezam-hd-cuberevo-hd/">интернет-форум Gomel-Sat</a>, где тусуются пользователи ресиверов Cuberevo (и не только).</li>
        </ul>
    </section>
    <footer>
        Последнее обновление сайта: 17 июля 2017
    </footer>
    </main>
</body>
</html>
