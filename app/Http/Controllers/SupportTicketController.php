<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

// Import Mailable Classes
use App\Mail\TicketSubmittedMail;
use App\Mail\TicketReplyMail;
use App\Mail\TicketResolvedMail;

class SupportTicketController extends Controller
{
    // ==========================================
    // SISI USER
    // ==========================================

    /**
     * User mengirimkan tiket / pengaduan baru
     */
    public function submitTicket(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        $validator = Validator::make($request->all(), [
            'category' => 'required|in:payment_issue,bug_report,feature_request,general_inquiry',
            'subject'  => 'required|string|max:255',
            'message'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Generate nomor tiket unik (Contoh: TIX-20260809-ABCD)
            $ticketNumber = 'TIX-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));

            $ticket = SupportTicket::create([
                'user_id'       => $user->id,
                'ticket_number' => $ticketNumber,
                'category'      => $request->category,
                'subject'       => $request->subject,
                'message'       => $request->message,
                'status'        => 'open',
            ]);

            // Kirim Email via Mailable & Blade
            Mail::to($user->email)->send(new TicketSubmittedMail($user, $ticket));

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Tiket berhasil dikirim. Silakan cek email Anda untuk informasi lebih lanjut.',
                'data' => $ticket
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Submit Ticket Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal mengirim tiket.'], 500);
        }
    }

    /**
     * User melihat daftar tiket miliknya sendiri (List)
     */
    public function myTickets()
    {
        $user = Auth::guard('sanctum')->user();
        $tickets = SupportTicket::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();

        return response()->json(['status' => 'success', 'data' => $tickets], 200);
    }

    /**
     * User melihat detail spesifik 1 tiket miliknya
     */
    public function showMyTicket($id)
    {
        $user = Auth::guard('sanctum')->user();
        $ticket = SupportTicket::where('id', $id)->where('user_id', $user->id)->first();

        if (!$ticket) {
            return response()->json(['status' => 'error', 'message' => 'Tiket tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $ticket], 200);
    }

    // ==========================================
    // SISI ADMIN
    // ==========================================

    /**
     * Laporan Statistik Tiket untuk Dashboard Admin
     */
    public function adminStats()
    {
        $stats = [
            'total_tickets' => SupportTicket::count(),
            'status_open' => SupportTicket::where('status', 'open')->count(),
            'status_in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'status_resolved' => SupportTicket::where('status', 'resolved')->count(),
            'status_closed' => SupportTicket::where('status', 'closed')->count(),
            // Tambahan laporan berdasarkan kategori pengaduan
            'category_payment' => SupportTicket::where('category', 'payment_issue')->count(),
            'category_bug' => SupportTicket::where('category', 'bug_report')->count(),
            'category_feature' => SupportTicket::where('category', 'feature_request')->count(),
            'category_general' => SupportTicket::where('category', 'general_inquiry')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Laporan statistik tiket berhasil diambil.',
            'data' => $stats
        ], 200);
    }

    /**
     * Admin melihat semua tiket (List & bisa di-filter berdasarkan status)
     */
    public function adminIndex(Request $request)
    {
        $query = SupportTicket::with('user:id,name,email,phone_number')->orderBy('created_at', 'desc');

        // Filter status (open, in_progress, resolved) via query parameter ?status=open
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        return response()->json(['status' => 'success', 'data' => $query->get()], 200);
    }

    /**
     * Admin melihat detail spesifik 1 tiket untuk dibaca sebelum membalas
     */
    public function adminShow($id)
    {
        $ticket = SupportTicket::with('user:id,name,email,phone_number')->find($id);

        if (!$ticket) {
            return response()->json(['status' => 'error', 'message' => 'Tiket tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $ticket], 200);
    }

    /**
     * Admin membalas tiket (Progressing / Update / Tanya Info)
     */
    public function adminReply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reply_message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $ticket = SupportTicket::with('user')->find($id);

        if (!$ticket) {
            return response()->json(['status' => 'error', 'message' => 'Tiket tidak ditemukan.'], 404);
        }

        $ticket->update([
            'status' => 'in_progress', 
            'admin_reply' => $request->reply_message
        ]);

        // Kirim Email via Mailable & Blade
        Mail::to($ticket->user->email)->send(new TicketReplyMail($ticket, $request->reply_message));

        return response()->json(['status' => 'success', 'message' => 'Balasan berhasil dikirim ke user.', 'data' => $ticket], 200);
    }

    /**
     * Admin menyelesaikan tiket (Mark as Done)
     */
    public function adminResolve(Request $request, $id)
    {
        $ticket = SupportTicket::with('user')->find($id);

        if (!$ticket) {
            return response()->json(['status' => 'error', 'message' => 'Tiket tidak ditemukan.'], 404);
        }

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => Carbon::now()
        ]);

        $finalMessage = $request->input('final_message', '');

        // Kirim Email via Mailable & Blade
        Mail::to($ticket->user->email)->send(new TicketResolvedMail($ticket, $finalMessage));

        return response()->json(['status' => 'success', 'message' => 'Tiket berhasil diselesaikan. Email resolusi telah dikirim.', 'data' => $ticket], 200);
    }
}