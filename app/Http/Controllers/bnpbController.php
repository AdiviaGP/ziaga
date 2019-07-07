<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\content;
use Auth;
use App\User;
use App\category;
use DB;
use paginate;

class bnpbController extends Controller
{
  public function dashboard(content $content){
    //Declare
    $statusArtikel = "Normal";
    $statusViewer = "Normal";
    $keputusan = "Normal";
    $startDate = new \DateTime(Date('Y-m-d', strtotime('-7 days')));
    $endDate = new \DateTime(Date('Y-m-d', strtotime('+0 days')));
    $interval = \DateInterval::createFromDateString('1 day');
    $days = new \DatePeriod($startDate, $interval, $endDate);


    //Regresi Viewer
    $totalView = array(); $i=0;
    foreach($days as $day){
      $viewData = DB::table('views')
      ->select(DB::raw('count(*) as total'))
      ->where(DB::raw('DATE_FORMAT(viewed_at, "%Y-%m-%d")'), $day->format('Y-m-d'))
      ->groupBy('viewed_at')
      ->get();
      $totalView[$i] = $viewData->count();
      $i++;
    }
    $prediksiView = $totalView[0];
    for($j=1; $j<sizeof($totalView); $j++){
      $prediksiView = floor(abs($prediksiView - $totalView[$j] /2));
    }
    $viewToday = DB::table('views')
    ->select(DB::raw('count(*) as total'))
    ->where(DB::raw('DATE_FORMAT(viewed_at, "%Y-%m-%d")'), date('Y-m-d'))
    ->groupBy('viewed_at')
    ->get();
    $viewToday = $viewToday->count();

    if($viewToday < $prediksiView) $statusViewer = "Kekurangan";
    elseif($viewToday = $prediksiView) $statusViewer = "Normal";
    elseif($viewToday > $prediksiView) $statusViewer = "Berlebih";


    //Regresi Artikel
    $totalArtikel = array(); $i=0;
    foreach($days as $day){
      $artikelData = DB::table('contents')
      ->select(DB::raw('count(*) as total'))
      ->where('status_id',1)
      ->where('updated_at', $day->format('Y-m-d'))
      ->groupBy('updated_at')
      ->get();
      $totalArtikel[$i] = $artikelData->count();
      $i++;
    }
    $prediksiArtikel = $totalArtikel[0];
    for($j=1; $j<sizeof($totalArtikel); $j++){
      $prediksiArtikel = ceil(abs($prediksiArtikel - $totalArtikel[$j] /2));
    }
    $artikelToday = DB::table('contents')
    ->select(DB::raw('count(*) as total'))
    ->where('status_id',1)
    ->where('updated_at', date('Y-m-d'))
    ->groupBy('updated_at')
    ->get();
    $artikelToday = $artikelToday->count();

    if($artikelToday < $prediksiArtikel) $statusArtikel = "Kekurangan";
    elseif($artikelToday = $prediksiArtikel) $statusArtikel = "Normal";
    elseif($artikelToday > $prediksiArtikel) $statusArtikel = "Berlebih";


    //Membuat Keputusan
    if    ($statusViewer == "Kekurangan" && $statusArtikel == "Kekurangan") $keputusan = "Butuh Tambahan Artikel";
    elseif($statusViewer == "Normal" && $statusArtikel == "Kekurangan") $keputusan = "Pertahankan Kondisi";
    elseif($statusViewer == "Berlebih" && $statusArtikel == "Kekurangan") $keputusan = "Butuh Tambahan Artikel";
    elseif($statusViewer == "Kekurangan" && $statusArtikel == "Normal") $keputusan = "Evaluasi Artikel";
    elseif($statusViewer == "Normal" && $statusArtikel == "Normal") $keputusan = "Pertahankan Kondisi";
    elseif($statusViewer == "Berlebih" && $statusArtikel == "Normal") $keputusan = "Butuh Tambahan Artikel";
    elseif($statusViewer == "Kekurangan" && $statusArtikel == "Berlebih") $keputusan = "Evaluasi Artikel";
    elseif($statusViewer == "Normal" && $statusArtikel == "Berlebih") $keputusan = "Evaluasi Artikel";
    elseif($statusViewer == "Berlebih" && $statusArtikel == "Berlebih") $keputusan = "Pertahankan Kondisi";


    $archive = content::where('status_id',0)->count();
    $post = content::where('status_id',1)->count();
    $trash = content::where('status_id',2)->count();
    $contentviewer = DB::table('contents')
      ->join('views', 'contents.id','=','views.viewable_id')
      ->join('categories', 'contents.category_id','=','categories.id')
      ->where('contents.status_id',1)
      ->select('title', 'kategori', DB::raw('count(viewable_id) as viewer'))
      ->groupBy('kategori')
      ->get();
    $label = DB::table('contents')
    ->join('categories', 'contents.category_id','=','categories.id')
    ->where('contents.status_id',1)
    ->select('kategori as name', DB::raw('count(category_id) as y'))
    ->groupBy('kategori')
    ->get();
    $vieww = DB::table('views')
    ->select(DB::raw('DATE_FORMAT(views.viewed_at, "%Y,%m,%d") as x'), DB::raw('count(*) as y'))
    ->groupBy('x')
    ->get();
    return view('dashboard.BNPB.index', compact('archive','post','trash','label','contentviewer', 'vieww', 'keputusan', 'statusViewer', 'statusArtikel'));
  }

  // Kontributor
  public function kontributor(){
    $kontributor = DB::table('contents')
    ->join('users', 'contents.user_id','=','users.id')
    ->where('users.role_id',0)->where('contents.status_id',1)
    ->select('namaDepan', 'namaBelakang', DB::raw('count(user_id) as total'))
    ->groupBy('user_id')
    ->get();
    return view('dashboard.BNPB.contributors',compact('kontributor'));
  }

  // list data
  public function posts(){
    $content = content::where('status_id',1)->get();
    return view('dashboard.BNPB.posts', compact('content'));
  }

  //Pending Posts
  public function pending_posts(){
    $content = content::where('status_id',3)->get();
    return view('dashboard.BNPB.pending-posts', compact('content'));
  }

  // kategori
  public function kategori(){
    $kategori = category::all();
    return view('dashboard.BNPB.category', compact('kategori'));
  }
  public function createKategori(Request $request){

    $kategori = new category;
    $kategori -> kategori = $request->kategori;
    $kategori -> description = $request->description;
    if($request->hasfile('thumbnail'))
    {
      $getimageName = $request->thumbnail->getClientOriginalName();
      $request->thumbnail->move(public_path('images'), $getimageName);
      $kategori->thumbnail = $getimageName;
    }
    $kategori->save();
    return redirect()->back()->with('success','Data berhasil di simpan');

  }

  // form create
  public function postsCreate(){
    $categories = category::all();
    return view('dashboard.BNPB.create-post', compact('categories'));
  }
  // validation store data
  public function validation($request){
    return $this->validate($request, [
      'title' => 'required|max:255',
      'category_id' => 'required|max:255',
      'description' => 'required|max:255',
      'isi_artikel' => 'required',
      'thumbnail' => 'required',
      'thumbnail.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048'
    ]);
  }
  // store data create
  public function postsCreatePost(Request $request){
    $this->validation($request);
    switch ($request->input('status_id')) {

      // Archive
      case 0:
          $content = new content;
          $content -> title = $request->title;
          $content -> category_id = $request->category_id;
          $content -> description = $request->description;
          $content -> isi_artikel = $request->isi_artikel;
          $content -> user_id = Auth::user()->id;
          $content -> status_id = $request->status_id;
          if($request->hasfile('thumbnail'))
          {
            $getimageName = $request->thumbnail->getClientOriginalName();
            $request->thumbnail->move(public_path('images'), $getimageName);
            $content->thumbnail = $getimageName;
          }
          $content->save();
          return redirect()->back()->with('success','Data berhasil di Archive');
          break;

      // Publish
      case 1:
          $content = new content;
          $content -> title = $request->title;
          $content -> category_id = $request->category_id;
          $content -> description = $request->description;
          $content -> isi_artikel = $request->isi_artikel;
          $content -> user_id = Auth::user()->id;
          $content -> status_id = $request->status_id;
          if($request->hasfile('thumbnail'))
          {
            $getimageName = $request->thumbnail->getClientOriginalName();
            $request->thumbnail->move(public_path('images'), $getimageName);
            $content->thumbnail = $getimageName;
          }
          $content->save();
          return redirect()->back()->with('success','Data berhasil di Publish');
          break;
      }
  }

  public function postsEdit($id){
    $categories = category::all();
    $content = content::find($id);
    return view('dashboard.BNPB.edit-post', compact('content','categories'));
  }

  public function postUpdate(Request $request, $id){
    $content = content::find($id);
    $content -> title = $request->title;
    $content -> category_id = $request->category_id;
    $content -> description = $request->description;
    $content -> isi_artikel = $request->isi_artikel;
    $content -> user_id = Auth::user()->id;
    $content -> status_id = $request->status_id;
    if($request->hasfile('thumbnail'))
    {
      $getimageName = $request->thumbnail->getClientOriginalName();
      $request->thumbnail->move(public_path('images'), $getimageName);
      $content->thumbnail = $getimageName;
    }
    $content->save();
    return redirect()->back()->with('success','Data berhasil di edit');
}

    public function status(Request $request, $id){
      $content = content::find($id);
      switch ($request->input('status_id')) {

        case 0:
          $content -> status_id = $request->status_id;
          $content->save();
          return redirect()->back()->with('success','Data berhasil di archive');
        break;
        case 1:
          $content -> status_id = $request->status_id;
          $content->save();
          return redirect()->back()->with('success','Data berhasil di publish');
        break;
        case 2:
          $content -> status_id = $request->status_id;
          $content->save();
          return redirect()->back()->with('success','Data berhasil di hapus');
        break;
        case 3:
          $content -> status_id = $request->status_id;
          $content->save();
          return redirect()->back()->with('success','Data berhasil di pending');
        break;
        case 4:
          $content -> status_id = $request->status_id;
          $content->save();
          return redirect()->back()->with('success','Data berhasil di tolak');
        break;
      }
  }

  public function archives(){
    $content = content::where('status_id',0)->get();
    return view('dashboard.BNPB.archives',compact('content'));
  }
  public function trash(){
    $content = content::where('status_id',2)->get();
    return view('dashboard.BNPB.trash', compact('content'));
  }
  public function setting(){
    $auth = Auth::user()->id;
    $setting = user::find($auth);
    return view('dashboard.BNPB.settings', compact('setting','auth'));
  }
  public function settingUpdate(Request $request, $id){
    $auth = Auth::user()->id;
    $setting = user::find($auth);

    $setting -> namaDepan = $request -> namaDepan;
    $setting -> namaBelakang = $request -> namaBelakang;
    $setting -> alamat = $request -> alamat;
    $setting -> kota = $request -> kota;
    $setting -> provinsi = $request -> provinsi;
    $setting -> kodePos = $request -> kodePos;
    $setting -> tentangSaya = $request -> tentangSaya;
    $setting->save();

    return redirect()->back()->with('success','Data berhasil di edit');
}
  }
