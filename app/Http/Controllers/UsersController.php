<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Country;
use Auth;
use Session;
use DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Exports\usersExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;


class UsersController extends Controller
{
    //

    public function userLoginRegister(){
        // echo "test"; die;
        $meta_title = "User Login/Register - E-Com Website";
        return view('users.login_register')->with(compact('meta_title'));
    }

    public function login(Request $request){
        if($request->isMethod('post')){
            $data= $request->all();
            // echo "<pre>"; print_r($data); die;
            if(Auth::attempt(['email' => $data['email'], 'password' => $data['password']])){
                $userStatus= User::where('email', $data['email'])->first();
                if($userStatus->status == 0){
                    return redirect()->back()->with('flash_message_error', 'Your Account is not Activated Please confirm your e-mail to Activate.');
                }
                Session::put('frontSession', $data['email']);

                // if(empty(Auth::user()->email)){
                //     $data['user_email']= '';
                // }else{
                //     $data['user_email'] = Auth::user()->email;
                // }

                if(!empty(Session::get('session_id'))){
                    $session_id= Session::get('session_id');
                    DB::table('cart')->where('session_id', $session_id)->update(['user_email'=>$data['email']]);
                }

                return redirect('/cart');
            }else{
                return redirect()->back()->with('flash_message_error', 'Invalid Username or Password');
            }
        }
    }

    public function register(Request $request){
        if($request->isMethod('post')){
            $data= $request->all();
            // echo "<pre>"; print_r($data); die;
            // Check if User Already exists
            $userCount = User::where('email', $data['email'])->count();
            if($userCount > 0){
                return redirect()->back()->with('flash_message_error', 'Email already exists');
            }else{
                // echo "Success"; die;
                $user = new User;
                $user->name = $data['name'];
                $user->email = $data['email'];
                $user->password = bcrypt($data['password']);
                date_default_timezone_set('UTC');
                $user->created_at= date('Y-m-d H:i:s');
                $user->updated_at= date('Y-m-d H:i:s');
                $user->save();

                // Send Register Email
                // $email = $data['email'];
                // $messagData = ['email'=> $data['email'], 'name'=> $data['name']];
                // Mail::send('emails.register', $messagData, function($message) use($email){
                //     $message->to($email)->subject('Registration with E-Com Website');
                // });

                // Send Confirmation Email
                // Base64 code is used to prevent spam
                $email = $data['email'];
                $messageData = ['email'=> $data['email'], 'name'=> $data['name'], 'code'=> base64_encode($data['email'])];
                Mail::send('emails.confirmation', $messageData, function($message) use($email){
                    $message->to($email)->subject('Confirm your E-Com Account');
                });

                return redirect()->back()->with('flash_message_success', 'Please Confirm Your Email to activate your account!');

                if(Auth::attempt(['email' => $data['email'], 'password' => $data['password']])){
                    Session::put('frontSession', $data['email']);
                    if(!empty(Session::get('session_id'))){
                        $session_id= Session::get('session_id');
                        DB::table('cart')->where('session_id', $session_id)->update(['user_email'=>$data['email']]);
                    }
                    return redirect('/cart');
                }
            }
        }
    }

    public function forgotPassword(Request $request){
        if($request->isMethod('post')){
            $data= $request->all();
            // echo "<pre>"; print_r($data); die;
            $userCount = User::where('email', $data['email'])->count();
            if($userCount == 0){
                return redirect()->back()->with('flash_message_error', 'Email does not exists!');
            }
            // die;
            // Get Users Details 
            $userDetails = User::where('email', $data['email'])->first();
            
            // Generate Random Password 
            // echo $random_password = str_random(8); die;
           
            $random_password = str_random(8); 

             // Encode the password for Security
            // echo $new_password = bcrypt($random_password); die;
            $new_password = bcrypt($random_password);
            // Update Password 
            User::where('email', $data['email'])->update(['password'=> $new_password]);

            // Send Forgot Password Email Code
            $email = $data['email'];
            $name = $userDetails->name;
            
            $messageData = [
                'email'=>$email,
                'name'=> $name, 
                'password'=> $random_password
            ];
            Mail::send('emails.forgotpassword', $messageData, function($message) use($email){
                $message->to($email)->subject('New Password - E-Com Website');
            });

            return redirect('login-register')->with('flash_message_success', 'Please check your email for new Password');
        }
        return view('users.forgot_password');
    }

    public function confirmAccount($email){
        // echo $email = base64_decode($email); die;
        $email = base64_decode($email); 
        $userCount = User::where('email', $email)->count();
        if($userCount > 0){
            // echo "success";
            $userDetails = User::where('email', $email)->first();
            if($userDetails->status == 1){
                return redirect('login-register')->with('flash_message_error', 'Your Email account is already activated. You can login now');
            }else{
                User::where('email', $email)->update(['status'=> 1]);

                // Send Welcome Email
                $messageData = ['email'=> $email, 'name'=> $userDetails->name];
                Mail::send('emails.welcome', $messageData, function($message) use($email){
                    $message->to($email)->subject('Welcome to E-Com Website');
                });

                return redirect('login-register')->with('flash_message_success', 'Your Email account is activated. You can login now');
            }
        }else{
            abort(404);
        }
    }

    public function account(Request $request){
        $user_id = Auth::user()->id;
        $userDetails = User::find($user_id);
        // echo "<pre>"; print_r($userDetails); die;
        $countries = Country::get();
        if($request->isMethod('post')){
            $data = $request->all();
            // echo "<pre>"; print_r($data); die;
            if(empty($data['name'])){
                return redirect()->back()->with('flash_message_error', 'Please enter your Name to update your account');
            }
            if(empty($data['address'])){
                $data['address']= ''; 
             }
             if(empty($data['city'])){
                $data['city']= ''; 
             }
             if(empty($data['country'])){
                $data['country']= ''; 
             }
             if(empty($data['pincode'])){
                $data['pincode']= ''; 
             }
             if(empty($data['mobile'])){
                $data['mobile']= ''; 
             }
             
              
            $user= User::find($user_id);
            $user->name=  $data['name'];
            $user->address=  $data['address'];
            $user->city=  $data['city'];
            $user->state=  $data['state'];
            $user->country=  $data['country'];
            $user->pincode=  $data['pincode'];
            $user->mobile=  $data['mobile'];
            $user->save();
            return redirect()->back()->with('flash_message_success', 'Your Account details has been successfully Updated');
        }
        return view('users.account')->with(compact('countries', 'userDetails'));
    }

    public function chkUserPassword(Request $request){
        $data= $request->all();
        // echo "<pre>"; print_r($data); die;
        $current_password = $data['current_pwd'];
        $user_id = Auth::User()->id;
        $check_password = User::where('id', $user_id)->first();
        if(Hash::check($current_password, $check_password->password)){
            echo "true"; die;
        }else{
            echo "false"; die;
        }
    }

    public function updatePassword(Request $request){
        if($request->isMethod('post')){
            $data= $request->all();
            // echo "<pre>"; print_r($data); die;
            $old_pwd = User::where('id', Auth::User()->id)->first();
            $current_pwd = $data['current_pwd'];
            if(Hash::check($current_pwd, $old_pwd->password)){
                //Update Password
                $new_pwd = bcrypt($data['new_pwd']);
                User::where('id', Auth::User()->id)->update(['password'=> $new_pwd]);
                return redirect()->back()->with('flash_message_success', 'Password Updated Successfully!');
            }else{
                return redirect()->back()->with('flash_message_error', 'Current Password is Incorrect!');
            }
        }
    }

    public function logout(){
        Auth::logout();
        Session::forget('frontSession');
        Session::forget('session_id');
        return redirect('/');
    }

    public function checkEmail(Request $request){
        // Check if email already exists
        $data= $request->all();
        $userCount = User::where('email', $data['email'])->count();
            if($userCount > 0){
               echo "false";
            }else{
                echo "true"; die;
            }
    }

    public function viewUsers(){
        if(Session::get('adminDetails')['users_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error', 'You have no access for this module');
        }
        $users = User::get();
        return view('admin.users.view_users')->with(compact('users'));
    }

    public function exportUsers(){
        return Excel::download(new usersExport, 'users.xlsx');
    }

    public function viewUsersCharts(){
        // echo $current_month_users = User::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month)->count(); die;
        // echo $last_month_users = User::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->subMonth(1))->count(); die;
        // echo $last_to_last_month_users = User::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->subMonth(2))->count(); die;
        $current_month_users = User::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month)->count();
        $last_month_users = User::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->subMonth(1))->count();
        $last_to_last_month_users = User::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->subMonth(2))->count();
        return view('admin.users.view_users_charts')->with(compact('current_month_users', 'last_month_users', 'last_to_last_month_users'));
    }

    public function viewUsersCountriesCharts(){
        $getUserCountries = User::select('country', DB::raw('count(country) as count'))->groupBy('country')->get();
        $getUserCountries = json_decode(json_encode($getUserCountries), true);
        // echo "<pre>"; print_r($getUserCountries); die;
        // echo $getUserCountries[4]['count']; die;
        return view('admin.users.view_users_countries_charts')->with(compact('getUserCountries'));
    }
}
