@extends('layouts.admin')

@section('title', 'MobileSentrix API')

@section('content')
    <section class="section-pad bg-white">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <p class="eyebrow">Parts</p>
                    <h1 class="display-6 fw-bold mb-0">MobileSentrix API</h1>
                </div>
                <a class="btn btn-outline-primary" href="{{ route('admin.parts.index') }}"><i class="bi bi-box-seam me-2"></i>Parts</a>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="surface p-4 h-100">
                        <p class="eyebrow mb-2">Configuration</p>
                        <dl class="row small mb-0">
                            <dt class="col-6">Environment</dt>
                            <dd class="col-6 text-end">{{ $configStatus['environment'] }}</dd>
                            <dt class="col-6">Base URL</dt>
                            <dd class="col-6 text-end">{{ $configStatus['base_url'] ? 'Yes' : 'No' }}</dd>
                            <dt class="col-6">Consumer name</dt>
                            <dd class="col-6 text-end">{{ $configStatus['consumer_name'] ? 'Yes' : 'No' }}</dd>
                            <dt class="col-6">Consumer key</dt>
                            <dd class="col-6 text-end">{{ $configStatus['consumer_key'] ? 'Yes' : 'No' }}</dd>
                            <dt class="col-6">Consumer secret</dt>
                            <dd class="col-6 text-end">{{ $configStatus['consumer_secret'] ? 'Yes' : 'No' }}</dd>
                            <dt class="col-6">Access token</dt>
                            <dd class="col-6 text-end">{{ $configStatus['access_token'] ? 'Yes' : 'No' }}</dd>
                            <dt class="col-6">Access secret</dt>
                            <dd class="col-6 text-end">{{ $configStatus['access_token_secret'] ? 'Yes' : 'No' }}</dd>
                            <dt class="col-6">Stored tokens</dt>
                            <dd class="col-6 text-end">{{ $configStatus['stored_access_tokens'] ? 'Yes' : 'No' }}</dd>
                            <dt class="col-6">Last authenticated</dt>
                            <dd class="col-6 text-end">{{ $configStatus['last_authenticated_at']?->format('M j, Y g:i A') ?? 'Never' }}</dd>
                            <dt class="col-6">Scheduled sync</dt>
                            <dd class="col-6 text-end">{{ $configStatus['sync_enabled'] ? 'Yes' : 'No' }}</dd>
                            <dt class="col-6">Connection status</dt>
                            <dd class="col-6 text-end">{{ session('mobilesentrix_connection_status', 'Not tested') }}</dd>
                        </dl>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="surface p-4 h-100">
                        <p class="eyebrow mb-2">Local Data</p>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span>Synced categories</span>
                            <strong>{{ number_format($categoriesCount) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-2">
                            <span>API parts</span>
                            <strong>{{ number_format($partsCount) }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="surface p-4 h-100">
                        <p class="eyebrow mb-2">Connection</p>
                        @if ($missingCredentials)
                            <p class="muted small mb-3">One or more required credentials are not configured.</p>
                        @else
                            <p class="muted small mb-3">Credentials are configured. API calls use server-side OAuth headers.</p>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.parts.mobilesentrix.test') }}">
                                @csrf
                                <button class="btn btn-primary" type="submit"><i class="bi bi-wifi me-2"></i>Test Live Connection</button>
                            </form>
                            <form method="POST" action="{{ route('admin.parts.mobilesentrix.authorize') }}">
                                @csrf
                                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-key me-2"></i>{{ $configStatus['stored_access_tokens'] ? 'Re-authenticate' : 'Start Live Authentication' }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <form class="surface p-4 h-100" method="POST" action="{{ route('admin.parts.mobilesentrix.sync-categories') }}">
                        @csrf
                        <p class="eyebrow mb-2">Categories</p>
                        <label class="form-label" for="category_id_categories">Category ID</label>
                        <input class="form-control mb-3" id="category_id_categories" name="category_id" placeholder="Optional root category">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cloud-arrow-down me-2"></i>Sync Categories</button>
                    </form>
                </div>
                <div class="col-lg-4">
                    <form class="surface p-4 h-100" method="POST" action="{{ route('admin.parts.mobilesentrix.sync-parts') }}">
                        @csrf
                        <p class="eyebrow mb-2">Parts</p>
                        <label class="form-label" for="category_id_parts">Category ID</label>
                        <input class="form-control mb-3" id="category_id_parts" name="category_id" placeholder="Optional, for example 165">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Sync Parts</button>
                    </form>
                </div>
                <div class="col-lg-4">
                    <form class="surface p-4 h-100" method="POST" action="{{ route('admin.parts.mobilesentrix.refresh') }}">
                        @csrf
                        <p class="eyebrow mb-2">Single Part</p>
                        <label class="form-label" for="sku">SKU or product ID</label>
                        <input class="form-control mb-3" id="sku" name="sku" placeholder="MobileSentrix SKU, new SKU, or ID" required>
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-lightning-charge me-2"></i>Refresh Part</button>
                    </form>
                </div>
            </div>

            <div class="surface p-4">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                    <div>
                        <p class="eyebrow mb-1">Sync Logs</p>
                        <h2 class="h4 fw-bold mb-0">Recent Activity</h2>
                    </div>
                    <div class="small muted">
                        Last category sync: {{ $lastCategoryLog?->finished_at?->diffForHumans() ?? 'Never' }}<br>
                        Last parts sync: {{ $lastPartLog?->finished_at?->diffForHumans() ?? 'Never' }}
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Updated</th>
                                <th>Skipped</th>
                                <th>Failed</th>
                                <th>Finished</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($latestLogs as $log)
                                <tr>
                                    <td>{{ str_replace('_', ' ', $log->sync_type) }}</td>
                                    <td><span class="badge text-bg-{{ $log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'danger' : 'warning') }}">{{ $log->status }}</span></td>
                                    <td>{{ $log->created_count }}</td>
                                    <td>{{ $log->updated_count }}</td>
                                    <td>{{ $log->skipped_count }}</td>
                                    <td>{{ $log->failed_count }}</td>
                                    <td>{{ $log->finished_at?->format('M j, Y g:i A') ?? 'Running' }}</td>
                                    <td class="text-break">{{ $log->message }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8">No sync activity yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection
