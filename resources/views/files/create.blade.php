<x-app-layout
    :title="__('Share a file with a link')"
    :description="__('Upload a file up to :mb MB and get a short download link and a QR code. Free, no sign-up.', ['mb' => $maxMb])"
    :alternates="true"
    :trail="true">

    <x-page-hero :title="__('Share a file')" :lead="__('Drop a file here and get a short link you can send to anyone.')" icon="upload" />

    <div class="container narrow page-body">
        <form class="card" method="POST" action="{{ lroute('files.store') }}" enctype="multipart/form-data" data-upload data-max="{{ upload_limit_kb() * 1024 }}">
            @csrf
            <x-submission />
            <label class="dropzone">
                <span class="tool-icon"><x-i name="upload" :size="30" /></span>
                <strong>{{ __('Drop a file here or click to choose') }}</strong>
                <span class="muted">{{ __('Up to :mb MB', ['mb' => $maxMb]) }}</span>
                <input type="file" id="file" name="file" required>
            </label>

            <div class="dz-file" data-file hidden>
                <x-i name="file" :size="22" />
                <span class="name"></span>
                <span class="muted size"></span>
            </div>
            <div class="progress" hidden><span></span></div>

            <p class="field-error" data-error role="alert" @unless ($errors->has('file')) hidden @endunless>{{ $errors->first('file') }}</p>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg" data-loading="{{ __('Uploading…') }}">
                    <x-i name="link" :size="20" /><span>{{ __('Upload and get link') }}</span>
                </button>
            </div>
        </form>

        <article class="prose" style="margin-top: 36px">
            <h2>{{ __('How to share a file with a link') }}</h2>
            <p>{{ __('Choose a file on your phone or computer, press the upload button and copy the short link we create. Anyone who opens the link downloads the file — no sign-up needed.') }}</p>
            <h2>{{ __('What you can upload') }}</h2>
            <ul class="info-list">
                <li><x-i name="check" :size="18" />{{ __('Documents, photos, archives and other files up to :mb MB.', ['mb' => $maxMb]) }}</li>
                <li><x-i name="check" :size="18" />{{ __('Programs and scripts (.exe, .bat, .php and similar) are not accepted.') }}</li>
                <li><x-i name="check" :size="18" />{{ __('Please upload only files you have the right to share.') }}</li>
            </ul>
        </article>
    </div>
</x-app-layout>
