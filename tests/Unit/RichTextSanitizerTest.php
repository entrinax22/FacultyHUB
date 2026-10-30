<?php

use App\Services\RichTextSanitizer;

test('rich text sanitizer preserves supported formatting and removes unsafe markup', function () {
    $sanitizer = new RichTextSanitizer;

    $html = $sanitizer->sanitize(
        '<p><strong>Read</strong> this <a href="javascript:alert(1)" onclick="alert(2)">carefully</a>.</p><script>alert(3)</script>'
    );

    expect($html)
        ->toContain('<strong>Read</strong>')
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');
});

test('rich text sanitizer converts formatted and legacy plain text to plain text', function () {
    $sanitizer = new RichTextSanitizer;

    expect($sanitizer->toPlainText('<h2>Criteria</h2><ul><li>Accuracy</li></ul>'))
        ->toBe('Criteria Accuracy');
    expect($sanitizer->toPlainText("Legacy instructions\nfor students"))
        ->toBe('Legacy instructions for students');
    expect($sanitizer->toPlainText('Use <angle brackets> in the answer.'))
        ->toBe('Use <angle brackets> in the answer.');
});

test('nested and malformed payloads retain only safe formatting', function (string $payload) {
    $sanitizer = new RichTextSanitizer;
    $result = $sanitizer->sanitize($payload);

    expect($result)->not->toMatch('/<(script|svg|math|iframe|object|embed|img|div|span)\b|onerror|onclick|javascript:|vbscript:|data:/i');
    expect($sanitizer->sanitize($result))->toBe($result);
})->with([
    '<div><span><a href="javascript:alert(1)" onclick="alert(1)">Read</a></span></div>',
    '<div><script><a href="javascript:alert(1)">attack</a></script><p>Safe</p></div>',
    '<svg><foreignObject><p onclick="alert(1)">attack</p></foreignObject></svg><p>Safe</p>',
    '<math><mtext><img src=x onerror=alert(1)></mtext></math><p>Safe</p>',
    '<iframe srcdoc="<script>alert(1)</script>"></iframe><object><embed src=x></object>',
    '<div><span><a href="data:text/html,attack">Read</a><a href="vbscript:alert(1)">Read</a></span></div>',
    '<p><a href="java&#x09;script:alert(1)">Read</a><a href="&#106;avascript:alert(1)">Read</a></p>',
    '<div><p onclick=alert(1)><strong>Safe</div><img src=x onerror=alert(1)>',
    str_repeat('<div>', 200).'<a href="javascript:alert(1)">Read</a>'.str_repeat('</div>', 200),
]);

test('unsafe raw text elements and markup have no readable content', function (string $payload) {
    $sanitizer = new RichTextSanitizer;

    expect($sanitizer->toPlainText($sanitizer->sanitize($payload)))->toBe('');
})->with([
    '<script>alert(1)</script>', '<style>body{color:red}</style>',
    '<svg><text>attack</text></svg>', '<math><mi>attack</mi></math>',
    '<iframe>attack</iframe>', '<object>attack</object>', '<embed src=x>',
    '<p><br></p>', '<div><span></span></div>', '<!-- empty -->',
    '<p>&nbsp;&#8203;&#65279;</p>', '<custom></custom>',
]);

test('safe links and formatting survive sanitization', function () {
    $html = '<h2>Task</h2><p><strong>Bold</strong> <em>Italic</em> <u>Underlined</u></p><ul><li>One</li></ul><ol><li>Two</li></ol><a href="https://example.com" title="Reference">Read</a>';
    $sanitizer = new RichTextSanitizer;

    expect($sanitizer->sanitize($html))->toBe($html);
});

test('programming examples and escaped content survive repeated saves', function () {
    $sanitizer = new RichTextSanitizer;
    $plain = 'if (a < b && b > 0) { List<T> result; }';
    $html = $sanitizer->sanitize($plain);

    expect($sanitizer->toPlainText($html))->toBe($plain);
    expect($sanitizer->sanitize($html))->toBe($html);
    expect($sanitizer->toPlainText('<p>Use &lt;script&gt; as literal text.</p>'))
        ->toBe('Use <script> as literal text.');
});
