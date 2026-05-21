package com.itsme.app.viewmodel
import android.app.Application
import androidx.lifecycle.*
import com.itsme.app.ItsmeApplication
import com.itsme.app.data.entities.Article
import kotlinx.coroutines.launch

class ArticleViewModel(app: Application) : AndroidViewModel(app) {
    private val dao = (app as ItsmeApplication).database.articleDao()
    private val _tid = MutableLiveData(0)
    val articles: LiveData<List<Article>> = _tid.switchMap { if (it > 0) dao.getByTravel(it) else dao.getAll() }
    fun setTravel(id: Int) { _tid.value = id }
    fun insert(a: Article) = viewModelScope.launch { dao.insert(a) }
    fun update(a: Article) = viewModelScope.launch { dao.update(a) }
    fun delete(a: Article) = viewModelScope.launch { dao.delete(a) }
}
