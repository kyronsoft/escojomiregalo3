@extends('layouts.admin.master')

@section('title')
    Knowledgebase
    {{ $title }}
@endsection

@push('css')
@endpush

@section('content')
    @component('components.breadcrumb')
        @slot('breadcrumb_title')
            <h3>Knowledgebase</h3>
        @endslot
        <li class="breadcrumb-item active">Knowledgebase</li>
    @endcomponent
    <div class="container-fluid faq-section">
        <div class="row">
            <div class="col-12">
                <div class="knowledgebase-bg"><img class="bg-img-cover bg-center" src="{{ asset('assets/images/knowledgebase/bg_1.jpg') }}"
                        alt="looginpage"></div>
                <div class="knowledgebase-search">
                    <div>
                        <h3>How Can I help you?</h3>
                        <form class="form-inline" action="#" method="get">
                            <div class="form-group w-100 mb-0"><i data-feather="search"></i>
                                <input class="form-control-plaintext w-100" type="text" placeholder="Type question here"
                                    title="">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 xl-50 col-lg-6 box-col-6">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="media faq-widgets">
                            <div class="media-body">
                                <h5>Articles</h5>
                                <p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget
                                    dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes,
                                    nascetur ridiculus mus.</p>
                            </div><i data-feather="book-open"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 xl-50 col-lg-6 box-col-6">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="media faq-widgets">
                            <div class="media-body">
                                <h5>Knowledgebase</h5>
                                <p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget
                                    dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes,
                                    nascetur ridiculus mus.</p>
                            </div><i data-feather="aperture"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-12 xl-100 box-col-12">
                <div class="card bg-primary">
                    <div class="card-body">
                        <div class="media faq-widgets">
                            <div class="media-body">
                                <h5>Support</h5>
                                <p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget
                                    dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes,
                                    nascetur ridiculus mus.</p>
                            </div><i data-feather="file-text"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-12">
                <div class="header-faq">
                    <h5 class="mb-0">knowledge articles by category</h5>
                </div>
                <div class="row browse">
                    <div class="col-xl-4 xl-50 col-md-6">
                        <div class="browse-articles">
                            <h6><span><i data-feather="archive"></i></span>Quick Questions are answered</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Lorem Ipsum is simply dummy text of the printing
                                        </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"> </i>Lorem Ipsum has been the industry's standard
                                            dummy </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>When an unknown printer took a galley </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>But also the leap into electronic typesetting,
                                        </h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"> <i class="me-1"
                                            data-feather="arrow-right"></i><span>See More (40)</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 xl-50 col-md-6">
                        <div class="browse-articles">
                            <h6><span><i data-feather="archive"></i></span> Integrating WordPress with Your Website</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>It was popularised in the 1960s with the release
                                        </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Etraset sheets containing Lorem Ipsum passages
                                        </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Desktop publishing software like Aldus PageMaker
                                        </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Making this the first true generator on the
                                            Internet.</h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"><i class="me-1"
                                            data-feather="arrow-right"></i><span> See More (90) </span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 xl-50 col-md-6">
                        <div class="browse-articles">
                            <h6><span><i data-feather="archive"></i></span>WordPress Site Maintenance</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>The point of using Lorem Ipsum is that it has a
                                            more-or-less </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Normal distribution of letters, as opposed to
                                            using </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Lorem Ipsum, you need to be sure there isn't
                                            anything </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Repetition, injected humour, or
                                            non-characteristi </h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"> <i class="me-1"
                                            data-feather="arrow-right"></i><span>See More (40)</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 xl-50 col-md-6">
                        <div class="browse-articles browse-bottom">
                            <h6><span><i data-feather="archive"></i></span>Meta Tags in WordPress</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Nemo enim ipsam voluptatem quia voluptas sit
                                        </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i><span>Ipsum quia dolor sit amet,
                                                consectetur</span></h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i><span>Sed quia non numquam eius modi tempora
                                                incidunt</span></h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i><span>Lorem eum fugiat quo voluptas nulla
                                                pariatu</span></h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"> <i class="me-1"
                                            data-feather="arrow-right"></i><span>See More (90)</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 xl-50 col-md-6">
                        <div class="browse-articles browse-bottom">
                            <h6><span><i data-feather="archive"></i></span>WordPress in Your Language</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Desktop publishing software like Aldus
                                            PageMaker</h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Etraset sheets containing Lorem Ipsum passages
                                        </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>It was popularised in the 1960s with the
                                            release</h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Making this the first true generator on the
                                            Internet</h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"> <i class="me-1"
                                            data-feather="arrow-right"></i><span>See More (50)</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 xl-50 col-md-6">
                        <div class="browse-articles browse-bottom">
                            <h6><span><i data-feather="archive"></i></span>Know Your Sources</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>The point of using Lorem Ipsum </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>It has a more-or-less normal distribution of
                                            letters</h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Et harum quidem rerum facilis est et expedita
                                        </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Nam libero tempore, cum soluta nobis est
                                            eligendi </h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"> <i class="me-1"
                                            data-feather="arrow-right"></i><span>See More (26)</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 xl-50 col-md-6">
                        <div class="browse-articles browse-bottom">
                            <h6><span><i data-feather="archive"></i></span>Validating a Website</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>When our power of choice is untrammelled </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>It will frequently occur that pleasures </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>who fail in their duty through weakness </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>At vero eos et accusamus et iusto </h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"> <i class="me-1"
                                            data-feather="arrow-right"></i><span>See More (10)</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 xl-50 col-md-6">
                        <div class="browse-articles browse-bottom">
                            <h6><span><i data-feather="archive"></i></span>Quick Questions are answered</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Quis autem vel eum iure reprehenderit </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Ducimus blanditiis praesentium voluptatum </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Omnis voluptas assumenda est</h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Produces no resultant pleasure</h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"> <i class="me-1"
                                            data-feather="arrow-right"></i><span>See More (21)</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 xl-100">
                        <div class="browse-articles browse-bottom">
                            <h6><span><i data-feather="archive"></i></span>Integrating WordPress with Your Website</h6>
                            <ul>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Lorem Ipsum passage, and going through</h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>The first line of Lorem Ipsum, Lorem ipsum
                                        </h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>Thus comes from a line in section</h5>
                                    </a></li>
                                <li><a href="knowledge-detail.html">
                                        <h5><i data-feather="file-text"></i>First true generator on the Internet</h5>
                                    </a></li>
                                <li><a class="f-w-600 font-primary" href="knowledge-detail.html"> <i class="me-1"
                                            data-feather="arrow-right"></i><span>See More (34)</span></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    @endpush
@endsection
