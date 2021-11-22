{{-- HTML(Blade) --}}
{{-- formデータ送信 --}}
{{-- @csrf --}}

<!DOCTYPE html>
<head>
    <title>タイム同期</title>
</head>
<body>
{{-- {{dd($syncSite);}} --}}
<div>
    <h2>実行対象</h2>
    <h3>下記の現場情報をタイムに反映します。</h3>
    <div>
        <ul >
            <li>プレイスID：{{$syncSite['place_id']}}</li>
            <li>プレイス名：{{$syncSite['place_name']}}</li>
            <li>現場ID：{{$syncSite['site_id']}}</li>
            <li>現場名：{{$syncSite['name']}}</li>
        </ul>
    </div>
</div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <h3>エラーが発生しました。</h3>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

<form method="post" action="{{ route('sites/execute') }}">
    @csrf
    <input type="hidden" name="place_id" value="{{$syncSite['place_id']}}" />
    <input type="hidden" name="site_id" value="{{$syncSite['site_id']}}" />
    <input type="hidden" name="sync_target" value="2" />
    <button
        type="submit"
        name="sync"
    >同期</button>
</body>



