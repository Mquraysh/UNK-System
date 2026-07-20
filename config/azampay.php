<?php
// config/azampay.php

define('AZAMPAY_APP_NAME', 'UNK_System');
define('AZAMPAY_CLIENT_ID', '8d760c2a-ea2d-4436-8001-f47ac6fb5298'); // Weka yako hapa
define('AZAMPAY_CLIENT_SECRET', 'BumkVAagaZSwQQExVTqB1vV94DAZVHoQOWsS+yidzsSaNzvDNssJ/5HWg0V2g4Z0mKELBVOrGA7W4pY3LLtw8EXW7irwAFlgcwBft3TQao3tOqKTn/qJDxlF3RhHKM3DG721/9GOblHShh3Aedm6XdIFCOe/LsIlxmhDlbVPq0s07diKoI4aZP3b+Wo73pBB6I6H8b0Y5EdAnOHMeZEcyFruITKCyxTlH765somyyjOSQ94gJgf5h67KEHV03vysg13q53EFCnFmTXYwtRJ2FcAZXAgy1jW2APhcluFoO9nCOlKgFiymRm+NLM2OX/cfNl6ldomDGZzrrGFKZTthn2DOS47dB2woCVGOa5fP9XidEM9xBHSG8lBy/bMiofBtKbn0SUjaaFTRCKao8lX256SECxiFLMit2BbNx2QwG85JbPqp/eTsF3kEoHcq6Hxf+cJ+2CnAd89tNoyq5oHfM8L8/nblIEiWNCtLNurbmakT8T5TYchmCKSxxAVC7FcFC9OJT8hRUc9MNBsK9etrVMr5hg4YYW/zw6Ip0nwf9zzJH1NF+s4yYah7gVFnK0MLqpJwiXAH27NzSx9HxE7rwgM9O+SFtPVdYvlhuFJUvpB6fhhoKiiscjWgLYH5PC+ze+Zy1DweJ6tXaklv7rfQ2MFL77m8dOhKBSdUJL07pk8='); // Weka yako hapa
define('AZAMPAY_ENVIRONMENT', 'sandbox'); // sandbox au production

// API Endpoints
if (AZAMPAY_ENVIRONMENT === 'sandbox') {
    define('AZAMPAY_API_URL', 'https://sandbox.azampay.co.tz');
} else {
    define('AZAMPAY_API_URL', 'https://api.azampay.co.tz');
}

// Callback URLs
define('PAYMENT_SUCCESS_URL', 'https://unk_system.com/payment/success.php');
define('PAYMENT_FAILED_URL', 'https://unk_system.com/payment/failed.php');
define('PAYMENT_WEBHOOK_URL', 'https://unk_system.com/payment/webhook.php');
?>