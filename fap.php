<?php

$cachedStat = false;
$CACHE_DIR = getenv("CACHE_DIR") ? getenv('CACHE_DIR') : './cache';
$API_KEY = getenv("YOUTUBE_API_KEY");
if (!$API_KEY) die( 'YOUTUBE_API_KEY is missing');

$VIDEOS = array();
set_time_limit(0);

$WATCH_LIST = array();
/*
$htmlCommunityVoting = file_get_contents($CACHE_DIR.'/community-voting.html');
preg_match_all('@<div class="post-video">(.+)</div>@simU', $htmlCommunityVoting, $m);
foreach ($m[0] as $match ) {
  if (preg_match('@embed/([^"]+)"@', $match, $u) ) {
    $WATCH_LIST[] = $u[1];
  }
}
// $WATCH_LIST[] = 'nAqBqpMRK7E';
// $WATCH_LIST[] = 'sdtqOXH5svg';
// TODO: watch list
*/

function by_likes( $v1, $v2 ) { return $v2['like'] - $v1['like']; }

function nicePrint( $list ) {
  $index = 1;
  if (PHP_SAPI !== 'cli') echo '<pre>';
  echo sprintf( "%3s", '#' )." | ";
  echo sprintf( "%8s", 'VIEW')." | ";
  echo sprintf( "%5s", 'LIKE')." | ";
  echo sprintf( "%8s", 'DISLIKE')." | ";
  echo sprintf( "%8s", 'COMMENT')." | ";
  echo sprintf( "%13s", 'ID')." | ";
  echo sprintf( "%6s", "%")." | TITLE";
  echo PHP_EOL;

  foreach ($list as $item) {
    echo sprintf( "%3d", $index )." | ";
    echo sprintf( "%8d", $item['view'])." | ";
    echo sprintf( "%5d", $item['like'])." | ";
    echo sprintf( "%8d", $item['dislike'])." | ";
    echo sprintf( "%8d", $item['comment'])." | ";
    echo sprintf( "%13s", $item['id'])." | ";
    echo sprintf( "%5.1f", $item['like']  * 100 / $item['view'] )."% | ";
    echo $item['title'].PHP_EOL;
    $index++;
  }
  if (PHP_SAPI !== 'cli') echo '</pre>';
}

function httpGet($url) {
        // echo $url.PHP_EOL;
        $ch = curl_init( $url );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        // echo json_encode($result).PHP_EOL;
        curl_close($ch);
        return $result;
}


function initVideos() {
  global $VIDEOS, $WATCH_LIST, $CACHE_DIR, $API_KEY;
  foreach ( $WATCH_LIST as $video_ID) {
    // echo $video_ID.PHP_EOL;
    $VIDEOS[$video_ID] = array( 'id' => $video_ID, 'view' => 0, 'like' => 0, 'comment' => 0, 'loaded' => 0 );
    $fileSnippet = $CACHE_DIR .'/'. $video_ID.'.snippet.json';
    if (file_exists( $fileSnippet )) {
       $JSONs = file_get_contents($fileSnippet);
    } else {
       $urlSnippet = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id='. $video_ID . '&key=' . $API_KEY;
       $JSONs = httpGet($urlSnippet);
       file_put_contents( $fileSnippet, $JSONs);
    }
    $JSONs_DATA = json_decode($JSONs, true);
    $title = $JSONs_DATA['items'][0]['snippet']['title'];
    $VIDEOS[$video_ID]['title'] = $title;
  }
}

function readStat($video_ID) {
  global $VIDEOS, $cachedStat, $CACHE_DIR, $API_KEY;

  $fileStat = $CACHE_DIR.'/'.$video_ID.'.statistics.json';
  if ($cachedStat && file_exists( $fileStat)) {
     $JSON = file_get_contents($fileStat);
  } else {
     $url = 'https://www.googleapis.com/youtube/v3/videos?part=statistics&id='. $video_ID . '&key=' . $API_KEY;
     // echo $url. PHP_EOL;
     $JSON = httpGet($url);
     file_put_contents( $fileStat, $JSON);
  }
  $JSON_DATA = json_decode($JSON, true);
  $stat = $JSON_DATA['items'][0]['statistics'];
  if (isset($stat)) {
    $VIDEOS[$video_ID]['view'] = isset($stat['viewCount']) ? intval($stat['viewCount']) : 0;
    $VIDEOS[$video_ID]['like'] = isset($stat['likeCount']) ? intval($stat['likeCount']) : 0;
    $VIDEOS[$video_ID]['dislike'] = isset($stat['dislikeCount']) ? intval($stat['dislikeCount']) : 0;
    $VIDEOS[$video_ID]['comment'] = isset($stat['commentCount']) ? intval($stat['commentCount']) : 0;
  } 
  $VIDEOS[$video_ID]['loaded'] = 1;
  return $VIDEOS[$video_ID];
}

  if (PHP_SAPI != "cli" && isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(readStat($_GET['id']));
    die;
  }




initVideos();
if (PHP_SAPI == "cli" ) {
  foreach ( $VIDEOS as $video_ID => $v ) { readStat($video_ID); }
  $listVideos = array_values($VIDEOS);
  uasort($listVideos, 'by_likes');
  nicePrint($listVideos);
} else {
  $VIDEOS_JSON = json_encode($VIDEOS, 2, JSON_PRETTY_PRINT);
  echo <<<EOF
<!DOCTYPE html>
<html lang="en" ng-app="app">
<head>
  <meta charset="UTF-8">
  <title>FAP-FAP</title>
  <meta name="robots" content="noindex,nofollow" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css" crossorigin="anonymous" />
</head>
<body ng-controller="AppController">
  <script src="//cdnjs.cloudflare.com/ajax/libs/angular.js/1.2.28/angular.min.js"></script>
  <div class="table-responsive">
    <table class="table table-bordered table-striped table-sm">
	<thead class="thead-dark">
	  <th>#</th>
	  <th>views</th>
	  <th>like</th>
	  <th>dislike</th>
	  <th>comment</th>
	  <th>%</th>
	  <th>id</th>
	  <th style="text-align: left">title</th>
        </thead>
	<tbody ng-repeat="v in videos | orderBy : '-like'">
	  <th ng-bind="\$index+1"></th>
	  <td style="text-align:right"> 
		<span ng-if="v.loaded" ng-bind="v.view"></span>
		<span ng-if="!v.loaded">...</span>
  	  </td>

	  <td style="text-align:right"> 
		<span ng-if="v.loaded" ng-bind="v.like"></span>
		<span ng-if="!v.loaded">...</span>
  	  </td>

	  <td style="text-align:right"> 
		<span ng-if="v.loaded" ng-bind="v.dislike"></span>
		<span ng-if="!v.loaded">...</span>
  	  </td>
	  <td style="text-align:right"> 
		<span ng-if="v.loaded" ng-bind="v.comment"></span>
		<span ng-if="!v.loaded">...</span>
  	  </td>
	  <td style="text-align:right"> 
		<span ng-if="v.loaded && v.view" ng-bind="(v.like * 100 / v.view) | pct"></span>
		<span ng-if="!v.loaded">...</span>
  	  </td>

	  <th ng-bind="v.id"></th>
	  <th ng-bind="v.title"></th>
        </tbody>
    </table>
  </div>
  <script>
     VIDEOS = ${VIDEOS_JSON};
  </script>
  <script src="./fap.app.js"></script>
</body>
</html>
EOF;

}
