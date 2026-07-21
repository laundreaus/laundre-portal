<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','Laundré Portal')</title>
<style>
  :root{--cream:#F4EFE6;--cream-line:#E4DBCB;--green:#435E53;--green-d:#33473D;--accent:#C4703F;--ink:#2E3D36;}
  *{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:var(--ink);background:var(--cream)}
  .logo svg{height:28px;width:auto;display:block}
  a{color:var(--green)}
  .btn{border:none;border-radius:8px;padding:10px 16px;font-size:14px;font-weight:700;cursor:pointer}
  .btn-green{background:var(--green);color:#fff}.btn-green:hover{filter:brightness(1.1)}
  .btn-ghost{background:transparent;color:var(--green);border:1px solid var(--cream-line)}
  input{font-family:inherit;font-size:14px;color:var(--ink);background:#fff;border:1px solid var(--cream-line);border-radius:8px;padding:10px 12px;width:100%}
  input:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 2px rgba(67,94,83,.18)}
</style>
</head>
<body>@yield('body')</body>
</html>
