# PHP-клиент Дребеденег

PHP 8.2+, расширения soap, openssl и json. Composer необязателен:
можно подключить autoload.php из корня проекта. Исходный readme.md сохранён.

## Проверка на этом компьютере

Запуск в PowerShell:

~~~powershell
& C:\php\php.exe -d extension=soap -d extension=openssl bin/drebedengi.php --demo balance
& C:\php\php.exe -d extension=soap -d extension=openssl bin/drebedengi.php --demo records
& C:\php\php.exe -d extension=soap -d extension=openssl tests/run.php
& C:\php\php.exe -d extension=soap -d extension=openssl tests/live.php
~~~

Расширения включаются только для данного процесса, общий php.ini не меняется.
Если они уже включены в вашей установке PHP, уберите параметры -d.
Для процесса без диска Z: используйте UNC-путь

## Подключение своего аккаунта

Получите личный API ID по инструкции https://www.drebedengi.ru/?module=soap.
Задайте DREB_API_ID, DREB_LOGIN и DREB_PASSWORD в окружении процесса PHP.
Без --demo клиент требует все три значения; автоматического перехода на демо нет.
.env.example — перечень переменных, файл .env автоматически не загружается.
Пароль не передаётся в аргументах CLI и не записывается библиотекой на диск.

Пример использования из PHP:

~~~php
require 'autoload.php';

$client = new Drebedengi\Client(Drebedengi\Credentials::fromEnvironment());
$balance = $client->getBalance();
$records = $client->getRecordList([
    'r_period' => 0,
    'period_from' => '2026-09-01',
    'period_to' => '2026-09-30',
]);
~~~

CLI позволяет агенту вызывать API напрямую и получать JSON:
balance, records, currencies, categories, sources, places, tags, revision.
Например, сохраните параметры периода в файл:

~~~json
{"r_period":0,"period_from":"2026-09-01","period_to":"2026-09-30"}
~~~

~~~powershell
php bin/drebedengi.php records '@period.json'
~~~

records по умолчанию возвращает последние 20 записей каждого вида, а не всю историю.
Для всей истории передайте r_period=6. Суммы и ID сохраняются в исходном виде:
не приводите денежные значения к float. Поле sum при записи — целое число сотых
(12345 означает 123.45). Ответы могут содержать строковые числа и флаги t/f.

## Другие методы и запись

~~~powershell
php bin/drebedengi.php methods
php bin/drebedengi.php call getAccessStatus
php bin/drebedengi.php call getAccumList '[[]]'
php bin/drebedengi.php call setRecordList '@write-arguments.json' --write
~~~

methods выводит имена параметров в нужном порядке. JSON для call — массив
позиционных аргументов БЕЗ API ID, логина и пароля. Они добавляются автоматически.
Для setRecordList файл содержит [[{...первая запись...}, {...вторая запись...}]].
Для прямого PHP-вызова используйте $client->setRecordList($records).
client_id создаёт запись, server_id обновляет существующую; сохраняйте
соответствие ID, возвращённое сервером. Форматы полей описаны в resources/dd.wsdl.

call поддерживает все методы схемы с авторизацией apiId/login/pass.
getServerSubs и userRegister имеют другую сигнатуру и сюда не включены.
CLI требует --write для методов без префикса get и запрещает их в демо-режиме.
PHP-библиотека выполняет явно вызванные методы записи без дополнительного флага.
Автоматических повторов нет: при сетевой ошибке записи сначала проверьте
состояние сервера, чтобы не создать дубликат.

Соединение использует HTTPS с проверкой сертификата, тайм-аут подключения 10 с
и чтения 30 с. SOAP trace выключен. SOAP-ошибки заменяются сообщением без
содержимого ответа. Не выводите трассировки исключений и объекты транспорта
в публичный журнал: аргументы вызова содержат реквизиты.

Это библиотека и локальная CLI, не HTTP-сервис и не MCP-сервер.
CLI не обслуживает запросы через веб. Входные JSON и выгрузки с личными данными
храните вне публичного каталога веб-сервера.

## Источники и проверки

- API: https://www.drebedengi.ru/?module=soap
- WSDL: https://www.drebedengi.ru/soap/dd.ssl.wsdl
- Пример PHP: https://www.drebedengi.ru/?action=soapTest&module=soap

resources/dd.wsdl — официальная схема, полученная 30.09.2026.
resources/operations.json — порядок параметров её методов с авторизацией.
tests/run.php проверяет контракт, сериализацию SOAP без сети, параметры,
сохранность чисел и обработку ошибок. Требует также стандартные DOM/SimpleXML.
tests/live.php выполняет только чтение публичного демо-аккаунта.
Запись в живой аккаунт тестами не выполняется.

Описание назначения для заявки на API ID:
«Личный PHP-клиент для прямого доступа AI-ассистента к моему учёту в Дребеденьгах:
чтение балансов, операций и справочников, анализ расходов и доходов,
добавление и исправление записей по моим указаниям».
