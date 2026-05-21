package com.itsme.app

import android.app.Application
import com.itsme.app.data.AppDatabase

class ItsmeApplication : Application() {
    val database: AppDatabase by lazy { AppDatabase.getDatabase(this) }
}
