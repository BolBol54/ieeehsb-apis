<?php

namespace App\Http\Controllers\AuthApi;

use App\User;
use App\Committee;
use App\Status;
use App\Season;
use App\Role;
use App\Position;
use App\Volunteer;
use App\Participant;
use App\Http\Controllers\Controller as Controller;
use App\Http\Resources\Post\RegisterCollection;
use Egulias\EmailValidator\Exception\ExpectingCTEXT;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use JWTAuthException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class RegisterController extends Controller
{

protected $user;
    public function __construct(User $user)
    {
        // $this->user = $user;
    }
/**
     * @SWG\Get(
     *   path="/api/register/",
     *   summary="Registeration Form View",
     *   operationId="register",
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error"),
     *)
     **/
    public function registerPage(){
        $data = Role::all();
        return new RegisterCollection($data);
    }
 /**
     * @SWG\Post(
     *   path="/api/register/",
     *   summary="Add new user",
     *   operationId="register",
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error"),
     *@SWG\Parameter(
     *          name="firstName",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="lastName",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="facutly",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="university",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="DOB",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="email",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="type",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="integer",
     *     ),
     *@SWG\Parameter(
     *          name="password",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="password_confirmation",
     *          in="query",
     *      description="testing data",
     *          required=true,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="role",
     *          in="query",
     *      description="testing data",
     *          required=false,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="committee",
     *          in="query",
     *      description="testing data",
     *          required=false,
     *          type="string",
     *     ),
     *@SWG\Parameter(
     *          name="ex_options",
     *          in="query",
     *      description="testing data",
     *          required=false,
     *          type="string",
     *     ),


     *   )


     *
     */
    public function register(Request $request)
    {
        $req = $request;
        $type = $request->input('type');
        Input::merge(array_map('trim', Input::all()));
        $validator = Validator::make($request->all(), [
            'firstName' => 'required |string | max:50 | min:3',
            'lastName' => 'required |string | max:50 | min:3',
            'faculty' => 'nullable |string | max:30 |   min:3',
            'university' => 'nullable |string | max:30 | min:3',
            'DOB' => 'nullable|date_format:d-m-Y|before:today',
            'email' => 'required |string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation'=>'sometimes|required_with:password',
            'type' =>'required|string',
        ]);


        if ($request->input('type')=='volunteer')
        {$validator = Validator::make($request->all(), ['role' => 'required']);}

       // if ($request->input('role')=='ex_com')
       // {$validator = Validator::make($request->all(), ['ex_options' => 'required']);}


       if ($validator->fails()) {

         return response()->json(['errors'=>$validator->errors()]);
       }

         //if position EX-com
        $confirmation_code = str_random(30);
        $user= new User();
        $user->firstName= $request->input('firstName');
        $user->lastName= $request->input('lastName');
        $user->image= 'default.jpg';
        $user->faculty= $request->input('faculty');
        $user->university= $request->input('university');
        $user->DOB= $request->input('DOB');
        $user->email=$request->input('email');
        $user->confirmation_code  = $confirmation_code ;
        $user->password=app('hash')->make($request->input('password'));

        if ($request->input('type')== 'volunteer'){
        //   if ($request->input('role')!=='ex_com')
        //   {$validat = Validator::make($request->all(), ['committee' => 'required']);
        //  if ($validat->fails()) {

        //     return response()->json(['errors'=>$validat->errors()]);
        //   }
        // }
          $seasonId = Season::where('isActive',1)->value('id');
           $stat    = Status::where('name','deactivated')->value('id');
          if ($request->input('role')=='ex_com'){

            $vol = new Volunteer();
            $user->type = "volunteer";
            $user->save();
            $vol->user_id = $user->id;
            $vol->status_id = $stat;

            if($request->input('ex_options') != null)
            {
              $vol->position_id = Position::where('name',$request->input('ex_options'))->value('id');
              $vol->save();
              $volHis = DB::table('vol_history')->insertGetId(
                [
                  'vol_id' =>$vol->id,
                  'season_id' =>$seasonId,
                  'position_id' => Position::where('name',$request->input('ex_options'))->value('id'),
                ]
              );
            }
            else {
              return response()->json(['message'=>'Ex Options Required']);

            }
        }

        if ($request->input('role')=='highboard')
        {
          $vol = new Volunteer;
          $user->type = "volunteer";

          $seasonId = Season::where('isActive',1)->value('id');
          $committee = DB::table('committees')->where('name',($request->input('committee')))->value('id');
          $dirPos = Position::where('name','director')->value('id');
          $status = Status::where('name','activated')->value('id');
          // dircetor of this committee of that season which is active exists
          // volunteer =>position director => of this commitee  => where this season is active

          $director = DB::table('vol_committees')
            ->join('volunteers', 'vol_committees.vol_id', '=', 'volunteers.id')
                 ->where('vol_committees.season_id', '=',  $seasonId)
                 ->where('vol_committees.committee_id','=',$committee)
                 ->where('vol_committees.position_id','=',$dirPos )
                 ->where('volunteers.status_id' ,'=',$status)->first();
            if ($director != null)
            {
                return response()->json(['message'=>'This Committee Already Have Director. If You Already The Right Director For This Committee Contact With the chairperson']);
            } else {
              $user->save();
              $vol->user_id = $user->id;
              $vol->status_id = $stat;
            $vol->position_id = Position::where('name','director')->value('id');
            $vol->status_id = $stat;
            $vol->save();
            $volComm = DB::table('vol_committees')->insertGetId(
              [
                'vol_id' => $vol->id,
                'committee_id' => $committee,
                'season_id' => $seasonId,
                'position_id' => $dirPos
              ]
            );

            $volHis = DB::table('vol_history')->insertGetId(
              [
                'vol_id' =>$vol->id,
                'season_id' =>$seasonId,
                'position_id' => Position::where('name','director')->value('id'),
              ]
            );
            }
        }

        if ($request->input('role')=='volunteer')
        {
          $committee = DB::table('committees')->where('name',($request->input('committee')))->value('id');
          $vol = new Volunteer;
          $user->type = "volunteer";
          $user->save();
          $vol->user_id = $user->id;
          $vol->position_id = Position::where('name','volunteer')->value('id');
          $vol->status_id = $stat;
          $vol->save();
          $volHis = DB::table('vol_history')->insertGetId(
            [
              'vol_id'=>$vol->id,
              'season_id' =>$seasonId,
              'position_id' => Position::where('name','volunteer')->value('id'),
            ]
          );
          $volComm = DB::table('vol_committees')->insertGetId(
            [
              'vol_id' => $vol->id,
              'committee_id' => $committee,
              'season_id' => $seasonId,
              'position_id' => Position::where('name','volunteer')->value('id'),
            ]
          );
        }
        $us =$vol;
      }
      else {
        $par = new Participant();
          $user->type = "participant";
        $user->save();
        $par->user_id = $user->id;
        $par->save();
        $us = $par;
      }
        // send activation email
        Mail::send('/emails.verify', compact(['type', 'req', 'user','confirmation_code']), function($message) use ($req,$us) {
            $message->to($this->MailTarget($req,$us), 'user')->subject('Verify an email address');
        });

        if ($user->id) {
            return response()->json(['response' => 'success', 'message' => 'Registration is Successful, please wait until your account being activated']);
        }else{
            return response()->json(['response' => 'failed', 'message' => 'Registration has failed, please check your data again!']);
        }
    }


    //  mail target
    public function MailTarget(Request $request, $us)
    {
      $email = 'ieeehelwanstudentbranch@gmail.com';

        // if participant register
        $user = User::query()->findOrFail($us->user_id);
        if ($user->type == 'participant') {
          $email = $user->email;
        }
      elseif($user->type == 'volunteer')
        {
          // $committee = DB::table('committees')->where('name',($request->input('committee')))->value('id');



        if ($request->input('role')=='ex_com' && ($request->input('ex_options')=='chairperson') ){
          $email = 'ieeehelwanstudentbranch@gmail.com';
        }

        // if Ex-com(!Chairperson) register
        if ($request->input('role')=='ex_com' && ($request->input('ex_options')!='chairperson') ) {
          $seasonId = Season::where('isActive',1)->value('id');

            try {
              // user id of the volunteer who is chairperson of the season which is active
              $chairperson = DB::table('volunteers')
                           ->join('vol_history', function ($join) {
                           $join->on('volunteers.id', '=', 'vol_history.vol_id')
                            ->where('vol_history.season_id',$seasonId)
                            ->where('volunteers.position_id',  Position::where('name','chairperson')->value('id'));
                           })->get();

                $user = User::query()->findOrFail($chairperson->user_id);
                $email = $user->email;
            } catch (\Exception $e) {
              $email = 'ieeehelwanstudentbranch@gmail.com';
            }
        }

        // if High Board register
        if ($request->input('role')=='highboard') {
          $seasonId = Season::where('isActive',1)->value('id');
          $committee = DB::table('committees')->where('name',($request->input('committee')))->value('id');
          $ment = DB::table('volunteers')
                  ->join('vol_committees', function ($join) {
                  $join->on('volunteers.id', '=', 'vol_committees.vol_id')
                  ->where('vol_committees.season_id',Season::where('isActive',1)->value('id'))
                  ->where('vol_committees.committee_id', $committee)
                  ->where('volunteers.position_id', Position::where('name','mentor')->value('id'));
                })->get();
          $dir = DB::table('volunteers')
               ->join('vol_committees', function ($join) {
                $join->on('volunteers.id', '=', 'vol_committees.vol_id')
                ->where('vol_committees.season_id',$seasonId)
                ->where('vol_committees.committee_id', $committee)
                ->where('volunteers.position_id', Position::where('name','director')->value('id'));
               })->get();
            try {

                $mentor =User::query()->findOrFail($ment->user_id);
                $email = $mentor->email;

            } catch (\Exception $e) {
                $email = 'ieeehelwanstudentbranch@gmail.com';
                 // $email = 'engMarina97@gmail.com';

            }
        }

        // if volunteer register
        if ($request->input('role')=='volunteer') {

            try {
                if (User::query()->findOrFail($dir->user_id) != null) {
                    $director = User::query()->findOrFail($dir->user_id);
                    $email = $director->email;
                }


                elseif (User::query()->findOrFail($ment->user_id) != null) {
                    $mentor = User::query()->findOrFail($ment->user_id);
                    $email = $mentor->email;
                }else{
                  $email = 'ieeehelwanstudentbranch@gmail.com';
                }
            } catch (\Exception $e) {
              $email = 'ieeehelwanstudentbranch@gmail.com';
            }
        }
      }

        return $email;
    }
}
