<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ $link }}</link>
        <description>{{ $description }}</description>
        <language>en-gb</language>
        <atom:link href="{{ url()->current() }}" rel="self" type="application/rss+xml"/>
        <ttl>15</ttl>
@foreach ($items as $item)
        <item>
            <title>{{ $item->title }}</title>
            <link>{{ $item->link }}</link>
            <description>{{ $item->description }}</description>
            <guid isPermaLink="false">{{ $item->guid }}</guid>
            <pubDate>{{ $item->publishedAt->toRssString() }}</pubDate>
        </item>
@endforeach
    </channel>
</rss>
