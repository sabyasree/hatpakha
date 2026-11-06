<?php

// Bengali strings missing from Filament's own `bn` pack (desk is Bengali-only, D36).

return array (
  'management_schema' => 
  array (
    'actions' => 
    array (
      'label' => 'ইমেলে যাচাই কোড',
      'below_content' => 'লগইনের সময় পরিচয় যাচাইয়ের জন্য ইমেলে একটি সাময়িক কোড পান।',
      'messages' => 
      array (
        'enabled' => 'চালু',
        'disabled' => 'বন্ধ',
      ),
    ),
  ),
  'login_form' => 
  array (
    'label' => 'ইমেলে কোড পাঠান',
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
      ),
    ),
  ),
);
