package com.hxscreen.tv.ui

/**
 * Bridge for remote-control keys. The video surface is a native view that
 * can swallow focus, so D-pad events don't reliably reach Compose key
 * handlers. MainActivity intercepts them first and forwards here.
 */
class ControlsBus {
    /** Return true when the key was consumed. */
    var onOk: (() -> Boolean)? = null
}
