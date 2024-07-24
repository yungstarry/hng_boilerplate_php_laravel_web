<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvitationAcceptanceController extends Controller
{
    /**
     * Generate and store an invitation with email validation.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateInvitation(Request $request)
    {
        // Ensure the request has JSON content type
        if (!$request->isJson()) {
            return response()->json([
                'message' => 'Request must be in JSON format',
                'errors' => ['Invalid content type'],
                'status_code' => 400
            ], 400);
        }

        // Validate that the request has the required fields
        $data = $request->validate([
            'org_id' => 'required|exists:organisations,org_id',
            'email' => 'required|email',
            'expires_at' => 'required|date|after:now'
        ]);

        // Validate that the user with the provided email exists
        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json([
                'message' => 'User with the provided email does not exist',
                'errors' => ['User email not found'],
                'status_code' => 400
            ], 400);
        }

        // Validate that the user belongs to the specified organization
        $organization = Organisation::where('org_id', $data['org_id'])->first();
        if (!$organization) {
            return response()->json([
                'message' => 'Organization not found',
                'errors' => ['Organization not found'],
                'status_code' => 400
            ], 400);
        }

        if (!$organization->users->contains($user->id)) {
            return response()->json([
                'message' => 'User is not associated with the specified organization',
                'errors' => ['User does not belong to the specified organization'],
                'status_code' => 400
            ], 400);
        }

        // Generate a unique token for the invitation
        $token = Str::random(32);

        // Create the invitation
        $invitation = Invitation::create([
            'uuid' => (string) Str::uuid(),
            'org_id' => $data['org_id'],
            'link' => $token,
            'expires_at' => Carbon::parse($data['expires_at']),
        ]);

        return response()->json([
            'invitation' => $invitation,
            'message' => 'Invitation created successfully',
            'status' => 201
        ], 201);
    }

    /**
     * Handle GET request to accept an invitation.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptInvitation(Request $request)
    {
        $token = $request->query('token');

        // Validate the invitation token
        $invitation = Invitation::where('link', $token)->valid()->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid or expired invitation link',
                'errors' => ['Invalid invitation link format', 'Expired invitation link', 'Organization not found'],
                'status_code' => 400
            ], 400);
        }

        // Retrieve the user by email
        $userEmail = $invitation->email;
        $user = User::where('email', $userEmail)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'errors' => ['User not found'],
                'status_code' => 400
            ], 400);
        }

        // Get the organization and attach the user
        $organization = $invitation->organization;

        if (!$organization) {
            return response()->json([
                'message' => 'Organization not found',
                'errors' => ['Organization not found'],
                'status_code' => 400
            ], 400);
        }

        $organization->users()->attach($user->id);

        return response()->json([
            'message' => 'Invitation accepted, you have been added to the organization',
            'status' => 200
        ], 200);
    }

    /**
     * Handle POST request to accept an invitation.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptInvitationPost(Request $request)
    {
        // Ensure the request has JSON content type
        if (!$request->isJson()) {
            return response()->json([
                'message' => 'Request must be in JSON format',
                'errors' => ['Invalid content type'],
                'status_code' => 400
            ], 400);
        }

        // Define required fields
        $requiredFields = ['invitationLink'];

        // Check if the required fields are present in the request
        $data = $request->only($requiredFields);
        $missingFields = array_diff($requiredFields, array_keys($data));

        if (!empty($missingFields)) {
            return response()->json([
                'message' => 'Required fields missing',
                'errors' => ['Missing fields: ' . implode(', ', $missingFields)],
                'status_code' => 400
            ], 400);
        }

        // Validate the request data
        $data = $request->validate([
            'invitationLink' => 'required|string'
        ]);

        // Check if the invitation link exists
        $invitationExists = Invitation::where('link', $data['invitationLink'])->exists();

        if (!$invitationExists) {
            return response()->json([
                'message' => 'Invalid invitation link',
                'errors' => ['Invitation link does not exist'],
                'status_code' => 400
            ], 400);
        }

        // If the invitation link is valid, return success response
        return response()->json([
            'message' => 'Invitation accepted, you have been added to the organization',
            'status' => 200
        ], 200);
    }
}
