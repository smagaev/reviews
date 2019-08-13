<!--1) GET: add-idea - добавить идею (при этом у идеи сохраняется guid автора, дата создания идеи)-->
<!--Параметры:-->
<!--string $guid - это идентификатор пользователя, он будет текстовый в формате 6F9619FF-8B86-D011-B42D-00CF4FC964FF (никаких проверок не надо, пока что чисто его сразу сохраняем), обязательное поле-->
<!--varchar (140) $text - текст идеи, обязательное поле-->
<!---->
<!--Возвращает:-->
<!--в случае успеха возвращает id новой идеи-->
<!--{"result": "success," "id": 150}-->
<!--в случае неудачи-->
<!--{"result": "error," "message": "Text is required"}-->
<!---->
<!--2) GET: list-ideas - показывает идеи, отсортированные по количеству голосов за идею (сверху самые популярные идеи)-->
<!--Параметры:-->
<!--string order - если тут передать "new", то идеи будут отсортированы по дате создания (т.е. более новые идеи выше)-->
<!--int limit - сколько идей выдавать, если не указано, то по умолчанию 50-->
<!--int page - номер страницы-->
<!---->
<!--Формат ответа:-->
<!--[-->
<!--{"text": "Добавьте виджет погоды", "votes": 50}-->
<!--]-->
<!---->
<!--3)GET: vote - проголосовать/отменить голос за идею-->
<!--Параметры:-->
<!--string guid - id пользователя, два раза за одну идею одному пользователю голосовать нельзя-->
<!--int id - идентификатор идеи-->
<!--  -->
<!---->
<!--0. GET: delete?secret=55773&id=130 - если я передаю в delete secret ключ, то я могу удалять идею, не заходя в базу-->

<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../../vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

 function connect_DB(){
    $dsn = 'mysql:host=172.19.0.4;dbname=reviews;charset=utf8';
    $usr = 'root';
    $pwd = '12345678';
    return $pdo = new \Slim\PDO\Database($dsn, $usr, $pwd);
}

$app = new \Slim\App(["settings" => $config]);

# http://127.0.0.1/ideas/add?&guid="12dss-edsf-fdsf-sdsd-fsdf-sdfsd-df"&idea="text_of_idea"
$app->get('/ideas/add', function(Request $request, Response $response, $arg) use($app) {
    $parms = explode('&',$request->getUri()->getQuery());
    $guid = trim(urldecode(explode('=',$parms[1])[1]),"\'\"");
    $idea = trim(urldecode(explode('=',$parms[2])[1]),"\'\"");
    $insertStatement = connect_DB()->insert(array('guid', 'idea'))
        ->into('ideas')
        ->values(array($guid, $idea));
    $insertId = $insertStatement->execute();

    $insertStatement = connect_DB()->insert(array('guid', 'id_idea'))
        ->into('votes')
        ->values(array($guid, $insertId));
    $instId = $insertStatement->execute();
    $response->getBody()->write($insertId);
});

# http://127.0.0.1/ideas/list-ideas?&limit=12&offset=1&order=new
# http://127.0.0.1/ideas/list-ideas?
$app->get('/ideas/list-ideas', function(Request $request, Response $response, $arg) use($app) {
    $parms = explode('&',$request->getUri()->getQuery());
    foreach ($parms as $par => $val){
        $p = explode('=',$val);
        $getparms[$p[0]] = trim(urldecode($p[1]),"\'\"");
    }
    $getparms['order']=='new'? $order = 'date': $order = 'votes';
    (isset($getparms['limit']))? $limit = +$getparms['limit'] : $limit = 50;
    (isset($getparms['offset']))? $offset = $limit*($getparms['offset']): $offset = 0;

    $selectStatement = connect_DB()->select()
        ->from('ideas')
        ->orderBy($order)
        ->limit($limit, $offset);

    $stmt = $selectStatement->execute();
    $data = $stmt->fetchAll();
    if(!$data) $response->getBody()->write("Ideas are absent!")
    ?>
    <table>
        <?foreach ($data as $row) {?>
            <tr data-id = <?echo $row['id']?> >
                <td class="td id"><?echo $row['id']?></td>
                <td class="td guid"><?echo $row['guid']?></td>
                <td class="td idea"><?echo $row['idea']?></td>
                <td class="td date"><?echo $row['date']?></td>
                <td class="td votes"><?echo $row['votes']?></td>
            </tr>
        <?}?>
    </table>
    <style>
        table{max-width:100%; background:#efefef;}
        .td{border:1px solid #ade; padding:5px;}
        .id{width:5%}
        .guid{width:15%}
        .idea{width:65%}
        .date{width:10%}
        .votes{width:10%}
    </style>
<?});

#GET: /ideas/delete?secret=55773&id=22
$app->get('/ideas/delete', function(Request $request, Response $response, $arg){
    $parms = explode('&',$request->getUri()->getQuery());
    $secret = trim(urldecode(explode('=',$parms[0])[1])," \'\"");
    $id = trim(urldecode(explode('=',$parms[1])[1])," \'\"");
    if($secret == 55773 ) {
        $deleteStatement = connect_DB()->delete()
            ->from('votes')
            ->where('id_idea', '=', $id);
        $affectedRows2 = $deleteStatement->execute();
        $deleteStatement = connect_DB()->delete()
            ->from('ideas')
            ->where('id', '=', $id);
        $affectedRows1 = $deleteStatement->execute();
    }
    $affectedRows1&&$affectedRows2? $response->getBody()->write('Idea removed'): $response->getBody()->write('Idea not removed');
});

#http://127.0.0.1/ideas/votes?guid="12dss-edsf-fdsf-sdsd-fsdf-sdfsd-df"&id=20&type=up
#http://127.0.0.1/ideas/votes?guid="12dss-edsf-fdsf-sdsd-fsdf-sdfsd-df"&id=20&type=cansel
$app->get('/ideas/votes', function(Request $request, Response $response, $arg) use($app) {
    $parms = explode('&',$request->getUri()->getQuery());
    foreach ($parms as $par => $val){
        $p = explode('=',$val);
        $_parms[$p[0]] = trim(urldecode($p[1]),"\'\"\ ");
    }
    $guid = $_parms['guid'];
    $id = $_parms['id'];
    $type = $_parms['type'];

    $selectStatement = connect_DB()->select()
        ->from('votes')
        ->where('id_idea', '=', $id)
        ->where('guid', '=', $guid);

    $stmt = $selectStatement->execute();
    $data = $stmt->fetch();
    $vote = $data['votes'];

    $selectStatement2 = connect_DB()->select()
        ->from('ideas')
        ->where('id', '=', $id);
    $stmt2 = $selectStatement2->execute();
    $data = $stmt2->fetch();
    $votes = $data['votes'];



if ($_parms['type'] == 'up'){
    if($vote!=1){
        $insertStatement = connect_DB()->insert(array('id_idea', 'guid'))
            ->into('votes')
            ->values(array($id, $guid));
        $insertId = $insertStatement->execute();

        $updateStatement = connect_DB()
            ->update(array('votes' => $votes+1))
            ->table('ideas')
            ->where('id', '=', $id);
        $affectedRows = $updateStatement->execute();

        if($insertId&&$affectedRows) $response->getBody()->write('up');
    }
};
if ($_parms['type'] == 'cansel'){
    $deleteStatement = connect_DB()->delete()
        ->from('votes')
        ->where('id_idea', '=', $id)->where('guid', '=', $guid);
    $affectedRows = $deleteStatement->execute();

    $updateStatement = connect_DB()
        ->update(array('votes' => $votes-1))
        ->table('ideas')
        ->where('id', '=', $id);
    $affectedRows2 = $updateStatement->execute();

    if($affectedRows && $affectedRows2) $response->getBody()->write('cansel');
};
});

$app->run();
?>