<?php

namespace App\Http\Controllers;

use App\Comment;
use App\Chapter;
use App\Committee;
use App\Volunteer;
use App\Status;
use App\Events\PostEvent;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Post\PostCollection;
use App\Http\Resources\Post\PostResource;
use App\Post;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;


class PostController extends Controller
{

    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('type:volunteer');


    }
    public function chapterVols($chapterId)
    {
        $chapter = Chapter::findOrFail($chapterId);
            $chapterVols = array();
            foreach ($chapter->committee as $key => $comm) {
                foreach ($comm->volunteer as $key => $vol) {
                    array_push($chapterVols, $vol->id);
                }
            }
            array_push($chapterVols, $chapter->chairperson_id);
            return $chapterVols;
    }

    public function index($id)
    {
        if(Chapter::find($id) != null)
        {
            $chapter = Chapter::findOrFail($id);

            $chapterVols = self::chapterVols($id);

            $vol = Volunteer::where('user_id',JWTAuth::parseToken()->authenticate()->id)->first();
            if (in_array($vol->id,$chapterVols) ||$vol->position->name =='chairperson' || $vol->position->name == 'vice-chairperson') {
                $approved = Status::where('name','approved')->value('id');
                 $posts = $chapter->post()->where('status_id',$approved)->orderBy('created_at', 'desc')->paginate(50);
                 return PostCollection::collection($posts);
            }
        }
        else{

        $committee = Committee::findOrFail($id);

            $vol = Volunteer::where('user_id',JWTAuth::parseToken()->authenticate()->id)->first();
        $volPos = $committee->volunteer()->where('vol_id',$vol->id)->value('position');
    //Anyone in the committeee and the chairperson and the vice can see the posts
       if($volPos != null || ($vol->position->name == 'chairperson' || ($vol->position->name == 'vice-chairperson'))){

            $approved = Status::where('name','approved')->value('id');
            $posts = $committee->post()->where('status_id',$approved)->orderBy('created_at', 'desc')->paginate(50);
            return PostCollection::collection($posts);
         }
         else{
                return response()->json('you are not in comm post page');
         }


        // $posts = Post::orderBy('created_at', 'desc')->paginate(50);
        // return PostCollection::collection($posts);
}
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function create($committee)
    {
        # code...
    }

    public function storeGeneralPost( Request $request)
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }
        $vol = Volunteer::where('user_id', JWTAuth::parseToken()->authenticate()->id)->first();

            $post = new Post;
            $post->body = $request->body;
            $post->created_at = now();
            $post->creator = $vol->id;
            $post->post_type = 'general';
            $post->post_id = 0;
            if ($vol->position->name == 'chairperson' || $vol->position->name == 'vice-chairperson')
            {
                $post->status_id = Status::where('name', 'approved')->value('id');
                $post->save();

                return response()->json('Post Created Successfully');

            } else {
                dd('ss');
                $post->status_id = Status::where('name', 'pending')->value('id');
                $post->save();
                return response()->json('The Post is sent to the chairperson to be approved');


            }


    }
    public function postGeneral()
    {
        $posts = Post::where('post_type','general')->get();
        return PostCollection::collection($posts);

    }
    public function pendingGeneralPost()
    {
        $staus = Status::where('name','pending')->value('id');
        $posts = Post::where('post_type','general')->where('status_id',$staus)->get();
        return PostCollection::collection($posts);


    }
    public function store(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
              'body' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
         return response()->json(['errors'=>$validator->errors()]);
        }


        if(Chapter::find($id) == null)
        {
            return response()->json([
                'response' => 'Error',
                'message' =>  'Chapter Not Found',
            ]);
        }
        elseif (Chapter::find($id) != null)
        {
            $chapter = Chapter::findOrFail($id);

            $chapterVols = self::chapterVols($id);
            $vol = Volunteer::where('user_id',JWTAuth::parseToken()->authenticate()->id)->first();
            if ($vol->position->name =='chairperson' ||$vol->position->name == 'vice-chairperson' || $vol->id == $chapter->chairperson_id) {

            $chapter->post()->create(
            [
                'body' => $request->body,
                'created_at' =>now(),
                'creator' => $vol->id,
                'status_id' => Status::where('name','approved')->value('id'),

            ]);
                return response()->json([
                    'response' => 'Success',
                    'message' =>  'Post Has Been Created Successfully',
                ]);

        }
        elseif(in_array($vol->id,$chapterVols))
        {
             $chapter->post()->create(
            [

                'body' =>$request->body,
                'status_id' => Status::where('name','pending')->value('id'),
                'created_at' =>now(),
                'creator' => $vol->id,
            ]);
            return response()->json([
                'response' => 'Warning',
                'message' =>  'The Post Is Sent To The Chairperson To Be Approved',
            ]);
         }
            else{
                return response()->json([
                    'response' => 'Error',
                    'message' =>  'You are not allowed to create this post',
                ]);
            }

        }
        elseif(Committee::find($id) == null) {
            return response()->json([
                'response' => 'Error',
                'message' => 'Committee Not Found',
            ]);
        }
        else
        {
        $committee = Committee::findOrFail($id);
        $vol = Volunteer::where('user_id',JWTAuth::parseToken()->authenticate()->id)->first();
        $volPos = $committee->volunteer()->where('vol_id',$vol->id)->value('position');
        //anyone exept the comm volunteers and the chair / vice
        if ( $volPos != 'volunteer' &&($vol->position->name != 'chairperson' ||
            ($vol->position->name != 'vice-chairperson'))) {
            return response()->json([
                'response' => 'Error',
                'message' =>  'You Are Not Volunteer In This Committee',
            ]);
        }
        /*the cahirperson and vice can add posts in this committee
        Any Volunteer in the committee can add post but it will be sent to director to approve it
        */
        elseif($vol->position->name == 'chairperson' || ($vol->position->name == 'vice-chairperson' ||($volPos == 'director')))
        {
            $committee->post()->create(
            [
                'body' => $request->body,
                'created_at' =>now(),
                'creator' => $vol->id,
                'status_id' => Status::where('name','approved')->value('id'),

            ]);
            return response()->json([
                'response' => 'Success',
                'message' =>  'Post Created Successfully',
            ]);

        }
        else
        {
            $committee->post()->create(
            [

                'body' =>$request->body,
                'status_id' => Status::where('name','pending')->value('id'),
                'created_at' =>now(),
                'creator' => $vol->id,
            ]);
            return response()->json([
                'response' => 'Warning',
                'message' =>  'The Post is sent to the director to be approved',
            ]);

         }
        // event(new PostEvent($post));
        }
    }

    public function show($p)
    {
//        $post = Post::find($p);
        if ($post = Post::find($p)) {
            $vol = Volunteer::findOrFail($post->creator);
            if ($vol->user_id == JWTAuth::parseToken()->authenticate()->id) {
                return new PostResource($post);
            }
            else{
                return response()->json([
                    'response' => 'Error',
                    'message' =>  'You Are Not Allowed To See This Post',
                    ]);
            }
        }
        else {
            return response()->json([
                'response' => 'Error',
                'message' =>  'Post Not Found',
            ]);
        }
    }


    public function edit($p)
    {
        $post = Post::find($p);
        if ($post = Post::find($p)) {

            $vol = Volunteer::findOrFail($post->creator);
            if ($vol->user_id == JWTAuth::parseToken()->authenticate()->id) {
                return new PostResource($post);
            }
        }
        else {
            return response()->json([
                'response' => 'Error',
                'message' =>  'Post Not Found',
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $p)
    {
//         $post = Post::find($p);
         if ($post = Post::find($p)) {
             $vol = Volunteer::findOrFail($post->creator);
             if ($vol->user_id == JWTAuth::parseToken()->authenticate()->id) {
                 $validator = Validator::make($request->all(), [
                     'body' => 'nullable|string|min:2',
                 ]);
                 if ($validator->fails()) {

                     return response()->json(['errors' => $validator->errors()]);
                 }

                 $post->body = $request->body;
                 $post->update();
                 return response()->json([
                     'response' => 'Success',
                     'message' => 'The Post Is Updated Successfully',
                 ]);
             } else {
                 return response()->json([
                     'response' => 'Error',
                     'message' => 'Un Authenticated',
                 ]);
             }
         }
         else{
             return response()->json([
                 'response' => 'Error',
                 'message' =>  'Post Not Found',
             ]);
         }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($p)
    {
        // $post = Post::findOrFail($id);
        if ($post = Post::find($p)) {
            $vol = Volunteer::findOrFail($post->creator);
            if ($vol->user_id == JWTAuth::parseToken()->authenticate()->id) {
                Comment::where('post_id', $post->id)->delete();
                $post->delete();
                return response()->json([
                    'response' => 'Success',
                    'message' =>  'The Post Has Been Deleted Successfully',
                    ]);
            } else {
                return response()->json([
                'response' => 'Error',
                'message' =>  'Un Authenticated',
                 ]);
            }
         }
        else{
            return response()->json([
                'response' => 'Error',
                'message' =>  'Post Not Found',
                ]);
        }
    }
    public function pendingPost($id)
    {
        if(Chapter::find($id) != null)
        {
            $chapter = Chapter::findOrFail($id);

            $chapterVols = self::chapterVols($id);
            $vol = Volunteer::where('user_id',JWTAuth::parseToken()->authenticate()->id)->first();
            if ($vol->position->name =='chairperson' ||$vol->position->name == 'vice-chairperson' || $vol->id == $chapter->chairperson_id ) {

                    $pending = Status::where('name','pending')->value('id');
                $posts = $chapter->post()->where('status_id',$pending)
                ->orderBy('created_at', 'desc')->paginate(50);
                return PostCollection::collection($posts);
            }
            else{
                return response()->json([
                    'response' => 'Error',
                    'message' =>  'You Are Not Allowed To See This Page',
                ]);
            }
        }
       else
       {
            $committee = Committee::findOrFail($id);
            $vol = Volunteer::where('user_id',JWTAuth::parseToken()->authenticate()->id)->first();
            $volPos = $committee->volunteer()->where('vol_id',$vol->id)->value('position');
            //anyone exept the comm volunteers and the chair / vice
           if ($vol->position->name =='chairperson' || $vol->position->name == 'vice-chairperson' || $vol->position->name == 'director'
           || $vol->id == $committee->chapter->chairperson_id)
            {
                $pending = Status::where('name','pending')->value('id');
                $posts = $committee->post()->where('status_id',$pending)
                ->orderBy('created_at', 'desc')->paginate(50);
                return PostCollection::collection($posts);
            }
            else{
                return response()->json([
                    'response' => 'Error',
                    'message' =>  'You Are Not Allowed To See This Page',
                ]);
            }
        }
    }
    public function approvePost( Request $request)
    {
         $validator = Validator::make($request->all(), [
              'post' => 'required|numeric',
        ]);
             if ($validator->fails()) {
         return response()->json(['errors'=>$validator->errors()]);
        }
        $approved = Status::where('name','approved')->value('id');
        $post = Post::findOrFail($request->post);
        $post->status_id = $approved;
        $post->update();
        return response()->json([
            'response' => 'Success',
            'message' =>  'The Post Has Been Approved',
        ]);

    }
    public function disapprovePost( Request $request)
    {
         $validator = Validator::make($request->all(), [
              'post' => 'required|numeric',
        ]);
             if ($validator->fails()) {
         return response()->json(['errors'=>$validator->errors()]);
        }
        $post = Post::findOrFail($request->post);
        $post->delete();
        return response()->json([
            'response' => 'Success',
            'message' =>  'The Post Has Been Deleted',
        ]);
    }
}
