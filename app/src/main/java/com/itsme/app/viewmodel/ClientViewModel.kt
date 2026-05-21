package com.itsme.app.viewmodel
import android.app.Application
import androidx.lifecycle.*
import com.itsme.app.ItsmeApplication
import com.itsme.app.data.entities.Client
import kotlinx.coroutines.launch

class ClientViewModel(app: Application) : AndroidViewModel(app) {
    private val dao = (app as ItsmeApplication).database.clientDao()
    val all = dao.getAllClients()
    fun insert(c: Client) = viewModelScope.launch { dao.insert(c) }
    fun update(c: Client) = viewModelScope.launch { dao.update(c) }
    fun delete(c: Client) = viewModelScope.launch { dao.delete(c) }
}
