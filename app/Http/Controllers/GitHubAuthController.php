<?php namespace Korobi\Http\Controllers;


use Illuminate\Session\Store;
use Korobi\Authentication\UserAuthenticationInterface;
use Korobi\Authentication\UserPermissionsInterface;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User;

class GitHubAuthController extends BaseController {

    /**
     * @var UserPermissionsInterface
     */
    private $perms;
    /**
     * @var UserAuthenticationInterface
     */
    private $auth;

    public function __construct(SocialiteFactory $socialite, UserPermissionsInterface $perms, UserAuthenticationInterface $auth) {
        $this->socialite = $socialite;
        $this->perms = $perms;
        $this->auth = $auth;
    }

    public function getUserDetails(Store $session) {
        $user = $this->socialite->driver('github')->user();
        $this->storeUserDetails($user, $session);
        return redirect("/auth/test");
    }

    public function redirectToGitHub() {
        return $this->socialite->driver('github')->scopes([])->redirect();
    }

    public function logout(Store $session) {
        $session->flush();
        return back();
    }

    public function testAuth() {
        return view("partials.auth-info", ["user" => $this->auth]);
    }

    private function storeUserDetails(User $user, Store $session) {
        /** Session key      Purpose                      **/
        /** ********************************************* **/
        /** auth.username    The user's GitHub username.   */
        /** auth.id          User's GitHub user id.        */
        /** auth.admin       Whether the user is an admin. */

        $session->set("auth.username", $user->getNickname());
        $session->set("auth.id", $user->getId());
        if ($this->perms->isAdministrator($user->getId())) {
            $session->set("auth.admin", true);
        }
    }

}
