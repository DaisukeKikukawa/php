<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    // ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
} else {
    // ログインしていない
    header('Location: login.php');
    exit();
}

// 投稿を記録する
if (!empty($_POST)) {
    if ($_POST['message'] != '') {
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
        $message->execute(array(
            $member['id'],
            $_POST['message'],
            $_POST['reply_post_id']
        ));

        header('Location: index.php');
        exit();
    }
}

// 返信の場合
if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}

// いいね機能
if (isset($_REQUEST['good'])) {
    $good = $db->prepare('INSERT INTO favorites SET member_id=?, post_id=?,created=NOW()');
    $good->execute(array($_SESSION['id'], $_REQUEST['good']));
    header('Location: index.php');
    exit();
} elseif (isset($_REQUEST['quit-good'])) {
    $delete = $db->prepare('DELETE FROM favorites WHERE member_id=? AND post_id=?');
    $delete->execute(array($_SESSION['id'], $_REQUEST['quit-good']));
    header('Location: index.php');
    exit();
}

// 返信の場合
if (isset($_REQUEST['retweet_id'])) {
    $retweet_post = $db->prepare('SELECT * FROM posts WHERE id=?');
    $retweet_post->execute(array($_REQUEST['retweet_id']));
    $retweet_post = $retweet_post->fetch();
    // 元データのmessageとidを入れてINSERT
    $retweet = $db->prepare('INSERT INTO posts SET member_id=?, message=?, retweet_post_id=?, created=NOW()');
    $retweet->execute(array(
        $_SESSION['id'],
        $retweet_post['message'],
        $retweet_post['id']

    ));
} elseif (isset($_REQUEST['quit-retweet'])) {
    $quit_post = $db->prepare('SELECT * FROM posts WHERE id=?');
    $quit_post->execute(array($_REQUEST['quit-retweet']));
    $quit_post = $quit_post->fetch();

    if ($quit_post['retweet_post_id'] !== '0') {
        $delete = $db->prepare('DELETE FROM posts WHERE id=?');
        $delete->execute(array($quit_post['id']));
    } else {
        $delete = $db->prepare('DELETE FROM posts WHERE member_id=? AND retweet_post_id=?');
        $delete->execute(array(
            $_SESSION['id'],
            $quit_post['id']
        ));
    }
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
    $page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();


function retweetedPost($post)
{
    global $db;
    $retweeted_post = $db->prepare('SELECT retweet_post_id FROM posts WHERE member_id=? AND retweet_post_id=?');
    $retweeted_post->execute(array(
        $_SESSION['id'],
        $post['id']
    ));
    $retweeted_post = $retweeted_post->fetch();
    return $retweeted_post['retweet_post_id'];
}

function retweetCounts($post)
{
    global $db;
    $is_retweet_post_id = null;
    if ((int) $post['retweet_post_id'] === 0) {
        $is_retweet_post_id = $post['id'];
    } else {
        $is_retweet_post_id = $post['retweet_post_id'];
    }
    $retweet_numbers = $db->prepare('SELECT COUNT(retweet_post_id) as cnt FROM posts WHERE retweet_post_id=? ');
    $retweet_numbers->execute(array($is_retweet_post_id));
    $all_post = $retweet_numbers->fetch();
    return $all_post['cnt'];
}

function myRetweetCounts($post)
{
    global $db;
    $search_retweet_post_id = null;

    if ((int) $post['retweet_post_id'] === 0) {
        $search_retweet_post_id = $post['id'];
    } else {
        $search_retweet_post_id = $post['retweet_post_id'];
    }
    $retweet_numbers = $db->prepare('SELECT COUNT(retweet_post_id) as cnt FROM posts WHERE retweet_post_id=? AND member_id=?');
    $retweet_numbers->execute(array($search_retweet_post_id, $_SESSION['id']));
    $all_post = $retweet_numbers->fetch();
    return $all_post['cnt'];
}

function goodCheck($post)
{
    global $db;
    $is_good = $db->prepare('SELECT * FROM favorites WHERE member_id=? AND post_id=?');
    $is_good->execute(array($_SESSION['id'], $post['id']));
    $good = $is_good->fetch();
    return $good;
}

function goodCounts($post)
{
    global $db;
    $good_numbers = $db->prepare('SELECT COUNT(post_id) from favorites where post_id=?');
    $good_numbers->execute(array($post['id']));
    $good_numbers = $good_numbers->fetch();
    return $good_numbers[0];
}

// htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value)
{
    return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ひとこと掲示板</title>

    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>ひとこと掲示板</h1>
        </div>
        <div id="content">
            <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
            <form action="" method="post">
                <dl>
                    <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
                    <dd>
                        <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
                        <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
                    </dd>
                </dl>
                <div>
                    <p>
                        <input type="submit" value="投稿する" />
                    </p>
                </div>
            </form>

            <?php foreach ($posts as $post) : ?>
                <?php
                    $retweet = $db->prepare('SELECT * FROM posts WHERE member_id=? AND retweet_post_id=?');
                    $retweet->execute(array(
                        $_SESSION['id'],
                        $post['id']
                    ));
                    $retweet_record = $retweet->fetch();
                    ?>
                <?php
                    $rt_on = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=?');
                    $rt_on->execute(array($post['retweet_post_id']));
                    $rt_post = $rt_on->fetch();
                    ?>

                <div class="msg">
                    <?php if ($post['retweet_post_id'] == 0) : ?>
                        <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                        <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>
                        <?php else : ?>
                            <?php echo $post['name'] . 'さんがリツイートしました。' . '<br>';    ?>
                            <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                            <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>
                            <?php endif; ?>
                            [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>

                            <p class="day">
                                <!-- 課題：リツイートといいね機能の実装 -->
                                <span class="retweet">
                                    <?php

                                        if ($post['member_id'] === $_SESSION['id'] && $post['retweet_post_id'] !== '0') {
                                            ?>
                                        <!-- 自分のリツイートを押したら押したツイートを削除 -->
                                        <a href="index.php?quit-retweet=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>"><img class="retweet-image" src="images/retweet-solid-blue.svg"></a>
                                    <?php
                                            // <!-- 自分がリツイートした元のpostは青色のリツイート画像-->
                                        } elseif (myRetweetCounts($post) !== '0') {
                                            ?>
                                        <a href="index.php?quit-retweet=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>"><img class="retweet-image" src="images/retweet-solid-blue.svg"></a>

                                    <?php
                                        } else {
                                            ?>
                                        <a href="index.php?retweet_id=<?php echo h($post['id']); ?>"><img class="retweet-image" src="images/retweet-solid-gray.svg"></a>
                                    <?php
                                        }
                                        ?>
                                    <span style="color:green;"><?php echo retweetCounts($post); ?></span>
                                </span>





                                <?php if (goodCheck($post)) : ?>
                                    <a href="index.php?quit-good=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">
                                        <span class="favorite">
                                            <img class="favorite-image" src="images/heart-solid-red.svg"><span style="color:red;"><?php echo goodCounts($post); ?></span>
                                        </span>
                                    </a>
                                <?php else : ?>
                                    <!-- いいねしていない時 -->
                                    <a href="index.php?good=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">
                                        <span class="favorite">
                                            <img class="favorite-image" src="images/heart-solid-gray.svg"><span style="color:gray;"><?php echo goodCounts($post); ?></span>
                                        </span>
                                    </a>
                                <?php endif; ?>




                                <a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
                                <?php
                                    if ($post['reply_post_id'] > 0) :
                                        ?>
                                    <a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">
                                        返信元のメッセージ</a>
                                <?php
                                    endif;
                                    ?>
                                <?php
                                    if ($_SESSION['id'] == $post['member_id']) :
                                        ?>
                                    [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
                                <?php
                                    endif;
                                    ?>
                            </p>
                </div>
            <?php endforeach; ?>

            <ul class="paging">
                <?php
                if ($page > 1) {
                    ?>
                    <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
                <?php
                } else {
                    ?>
                    <li>前のページへ</li>
                <?php
                }
                ?>
                <?php
                if ($page < $maxPage) {
                    ?>
                    <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
                <?php
                } else {
                    ?>
                    <li>次のページへ</li>
                <?php
                }
                ?>
            </ul>
        </div>
    </div>
</body>

</html>