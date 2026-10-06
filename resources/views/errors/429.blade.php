@extends('errors.layout')
@section('code', '429')
@section('message', 'Too many requests')
@section('detail', 'You have hit a rate limit. Wait a moment and try again.')
