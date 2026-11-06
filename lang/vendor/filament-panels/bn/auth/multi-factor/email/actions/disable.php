<?php

// Bengali strings missing from Filament's own `bn` pack (desk is Bengali-only, D36).

return array (
  'modal' => 
  array (
    'form' => 
    array (
      'code' => 
      array (
        'label' => 'ইমেলে পাঠানো ৬ অঙ্কের কোড দিন',
        'validation_attribute' => 'কোড',
        'actions' => 
        array (
          'resend' => 
          array (
            'label' => 'ইমেলে নতুন কোড পাঠান',
            'notifications' => 
            array (
              'resent' => 
              array (
                'title' => 'ইমেলে নতুন কোড পাঠানো হয়েছে',
              ),
              'throttled' => 
              array (
                'title' => 'অনেকবার পাঠানো হয়েছে। নতুন কোড চাওয়ার আগে একটু অপেক্ষা করুন।',
              ),
            ),
          ),
        ),
        'messages' => 
        array (
          'invalid' => 'কোডটি ঠিক নয়।',
          'rate_limited' => 'অনেকবার চেষ্টা হয়েছে। একটু পরে আবার চেষ্টা করুন।',
        ),
      ),
    ),
  ),
);
