
<li class="nav-item">
    <a href="{{ route('posts.index') }}" class="nav-link {{ Request::is('posts*') ? 'active' : '' }}">
        <i class="nav-icon fas fa-home"></i>
        <p>Posts</p>
    </a>
</li>
