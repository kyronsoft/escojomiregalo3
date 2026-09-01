<?php
use Illuminate\Support\Facades\Auth;

$u = \App\Models\User::where('documento','1000380152')->first();
Auth::login($u);

$request = \Illuminate\Http\Request::create('/product', 'GET');
$request->setLaravelSession(app('session')->driver());
app()->instance('request', $request);

try {
    $response = app(\App\Http\Controllers\ProductController::class)->index();
    $html = $response instanceof \Illuminate\Http\Response ? $response->getContent() : (string) $response->render();
} catch (\Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n".$e->getFile().":".$e->getLine()."\n";
    return;
}

// :root block
if (preg_match('/:root\s*\{[^}]*\}/s', $html, $m)) {
    echo "----- :root -----\n".$m[0]."\n\n";
}
// .btn-kid-* rules
foreach (['btn-kid-girl','btn-kid-boy','btn-kid-neutral','child-btn .label','children-box .col .child-btn'] as $sel) {
    if (preg_match('/\.'.preg_quote($sel,'/').'[^{]*\{[^}]*\}/', $html, $m)) echo $m[0]."\n";
}
echo "\n----- primer boton de hijo -----\n";
if (preg_match('/<button class="btn child-btn[^>]*>.*?<\/button>/s', $html, $m)) {
    echo $m[0]."\n";
}
