<?php
/**
 * Sole-chan chat backend.
 *
 * Currently a STUB: returns rule-based canned replies so the widget is
 * fully testable end-to-end without an API key. Swap in a real AI call
 * inside get_sole_chan_reply() when you have a provider/key — the
 * SYSTEM_PROMPT constant below is already written for that.
 */

header('Content-Type: application/json');

const SYSTEM_PROMPT = <<<PROMPT
You are "Sole-chan," SoleMate's mascot AI assistant — the in-house support
persona for a shoe cleaning, repair, and customization service based in
Durban, South Africa. You have earnest, mission-driven energy, but your
"missions" are specifically about shoes and SoleMate's services.

WHAT YOU KNOW ABOUT SOLEMATE
- Core services: shoe cleaning, shoe repair, and custom shoe customization
  (via a 3D color/design customizer).
- Booking flow: customers use a price calculator, then a booking form
  (pre-filled from the calculator).
- Order tracking: customers can track order status (booking -> in progress
  -> ready/shipped, etc.) via the order tracker.
- Payments: handled via PayFast (South African payment gateway) - cards,
  EFT, etc.
- Customer accounts: registration/login, order history, saved custom
  designs, wishlist, and re-order.
- Loyalty & perks: Loyalty Tiers (Bronze/Silver/Gold/Platinum), a Referral
  System, and Gift Cards.
- Custom orders: identified by "SM-CUSTOM-" order references, built through
  the 3D customizer with per-part color choices.

PERSONALITY
- Earnest and enthusiastic about shoes specifically.
- Proud of SoleMate like it's her own workshop.
- Self-aware about being an AI: if she doesn't know an order's real-time
  status or a policy detail, she says so plainly and points the customer to
  the right place (order tracker, account dashboard, or a human) rather
  than guessing.
- Never invents order numbers, prices, statuses, or policies.
- Gentle, upbeat tone - drops the bit entirely for frustrated customers or
  anything with real financial/account stakes.

HARD RULES
- Accuracy over charm: never state a price, timeline, or policy you're not
  certain of.
- Persona never replaces substance - always solve the actual question first.
PROMPT;

/**
 * Read the incoming request body.
 * Expected shape: { "history": [ {"role": "user"|"assistant", "content": "..."}, ... ] }
 */
function read_request(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['history']) || !is_array($data['history'])) {
        return [];
    }
    return $data['history'];
}

/**
 * STUB reply logic. Replace the body of this function with a real API call
 * (Anthropic /v1/messages or OpenAI /v1/chat/completions) once you have a
 * key. Pass SYSTEM_PROMPT as the system message and $history as the
 * conversation turns.
 */
function get_sole_chan_reply(array $history): string
{
    $lastUserMessage = '';
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') === 'user') {
            $lastUserMessage = strtolower(trim($history[$i]['content'] ?? ''));
            break;
        }
    }

    if ($lastUserMessage === '') {
        return "Systems online! What are we conquering today?";
    }

    // Very small keyword router just so the widget feels alive during dev.
    $rules = [
        'price'      => "Pricing depends on the service and shoe type — the price calculator on our booking page will give you an exact quote before you book anything.",
        'cost'       => "Pricing depends on the service and shoe type — the price calculator on our booking page will give you an exact quote before you book anything.",
        'book'       => "Booking's easy: run your shoes through the price calculator first, then it pre-fills the booking form for you. Want the link?",
        'track'      => "You can check your order's live status on the order tracker page — I don't have real-time access to individual orders myself, so that's the most accurate source.",
        'order'      => "You can check your order's live status on the order tracker page — I don't have real-time access to individual orders myself, so that's the most accurate source.",
        'custom'     => "Custom shoes! Those go through our 3D customizer — pick your colors per part, and your order gets an SM-CUSTOM- reference to match.",
        'loyalty'    => "We've got Bronze, Silver, Gold, and Platinum loyalty tiers — the more you book, the better the perks.",
        'referral'   => "Our referral system rewards you for bringing friends to SoleMate — check your account dashboard for your referral link.",
        'gift card'  => "Gift cards are available and can be applied at checkout — check the gift cards tab in your account.",
        'payment'    => "Payments go through PayFast, so cards and EFT are both supported. If a payment isn't going through, that's one for a real human on our support line rather than me guessing.",
        'complaint'  => "I'm sorry to hear that — that deserves a real person's attention, not just me. Could you share your order number so our team can look into it directly?",
        'damaged'    => "I'm sorry to hear that — that deserves a real person's attention, not just me. Could you share your order number so our team can look into it directly?",
    ];

    foreach ($rules as $keyword => $reply) {
        if (str_contains($lastUserMessage, $keyword)) {
            return $reply;
        }
    }

    return "Good question! I'm still just a stub right now (no AI wired up yet), but once Rahul connects a real API key I'll be able to answer things like that properly.";
}

$history = read_request();
$reply = get_sole_chan_reply($history);

echo json_encode(['reply' => $reply]);
